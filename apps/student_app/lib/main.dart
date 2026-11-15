import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:material_ui/material_ui.dart';
import 'api_client.dart';
import 'package:video_player/video_player.dart';
import 'package:url_launcher/url_launcher.dart';

void main() => runApp(const StudentApp());

class StudentApp extends StatelessWidget {
  const StudentApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: "Alom's English Center — Student",
    debugShowCheckedModeBanner: false,
    theme: ThemeData(colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1769AA)), useMaterial3: true),
    home: const StudentGate(),
  );
}

class StudentGate extends StatefulWidget {
  const StudentGate({super.key});
  @override
  State<StudentGate> createState() => _StudentGateState();
}
class _StudentGateState extends State<StudentGate> {
  final api = MobileApi();
  bool loading = true;
  bool signedIn = false;
  @override
  void initState() {
    super.initState();
    api.restoreSession().then((restored) {
      if (!mounted) { return; }
      setState(() { signedIn = restored; loading = false; });
    });
  }
  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return signedIn
      ? StudentHome(api: api, onLogout: () => setState(() => signedIn = false))
      : StudentLogin(api: api, onLogin: () => setState(() => signedIn = true));
  }
}

class StudentLogin extends StatefulWidget {
  const StudentLogin({required this.api, required this.onLogin, super.key});
  final MobileApi api;
  final VoidCallback onLogin;

  @override
  State<StudentLogin> createState() => _StudentLoginState();
}

enum _LoginStage { credentials, phoneVerification, adminApproval }

class _StudentLoginState extends State<StudentLogin> {
  final email = TextEditingController();
  final password = TextEditingController();
  final code = TextEditingController();
  bool busy = false;
  String? error;
  String? notice;
  _LoginStage stage = _LoginStage.credentials;
  Timer? approvalTimer;

  @override
  void dispose() {
    approvalTimer?.cancel();
    email.dispose();
    password.dispose();
    code.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    setState(() { busy = true; error = null; notice = null; });
    try {
      final outcome = await widget.api.login(
        email.text.trim(),
        password.text,
        'Aloms Student App',
      );
      _handleOutcome(outcome);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> verifyPhone() async {
    setState(() { busy = true; error = null; });
    try {
      final outcome = await widget.api.verifyDevice(code.text);
      _handleOutcome(outcome);
    } on ApiException catch (exception) {
      if (mounted) {
        setState(() {
          error = exception.toString();
          if (exception.statusCode == 410 || exception.statusCode == 403) {
            stage = _LoginStage.credentials;
            widget.api.clearLoginChallenge();
          }
        });
      }
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  void _handleOutcome(LoginOutcome outcome) {
    if (!mounted) { return; }
    switch (outcome.state) {
      case LoginState.authenticated:
        approvalTimer?.cancel();
        widget.onLogin();
        return;
      case LoginState.verifyPhone:
        setState(() {
          stage = _LoginStage.phoneVerification;
          notice = outcome.message;
          error = null;
        });
        return;
      case LoginState.pendingApproval:
        setState(() {
          stage = _LoginStage.adminApproval;
          notice = outcome.message;
          error = null;
        });
        _startApprovalPolling();
        return;
    }
  }

  void _startApprovalPolling() {
    approvalTimer?.cancel();
    approvalTimer = Timer.periodic(
      const Duration(seconds: 15),
      (_) => checkApproval(silent: true),
    );
  }

  Future<void> checkApproval({bool silent = false}) async {
    if (busy) { return; }
    if (!silent && mounted) { setState(() { busy = true; error = null; }); }
    try {
      final outcome = await widget.api.checkDeviceStatus();
      _handleOutcome(outcome);
    } on ApiException catch (exception) {
      if (mounted && (exception.statusCode == 403 || exception.statusCode == 410)) {
        approvalTimer?.cancel();
        await widget.api.clearLoginChallenge();
        setState(() {
          stage = _LoginStage.credentials;
          error = exception.toString();
        });
      } else if (mounted && !silent) {
        setState(() => error = exception.toString());
      }
    } catch (exception) {
      if (mounted && !silent) { setState(() => error = exception.toString()); }
    } finally {
      if (!silent && mounted) { setState(() => busy = false); }
    }
  }

  Future<void> backToLogin() async {
    approvalTimer?.cancel();
    await widget.api.clearLoginChallenge();
    code.clear();
    if (mounted) {
      setState(() {
        stage = _LoginStage.credentials;
        notice = null;
        error = null;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = stage == _LoginStage.credentials
        ? 'Student App'
        : stage == _LoginStage.phoneVerification
            ? 'Verify your mobile number'
            : 'Waiting for academy approval';

    return Scaffold(
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 440),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(Icons.school, size: 64),
                Text(
                  "Alom's English Center",
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                Text(title, textAlign: TextAlign.center),
                const SizedBox(height: 24),
                if (stage == _LoginStage.credentials) ...[
                  TextField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: password,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Password',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: busy ? null : submit,
                    child: busy
                        ? const CircularProgressIndicator()
                        : const Text('Sign in'),
                  ),
                ] else if (stage == _LoginStage.phoneVerification) ...[
                  const Icon(Icons.sms_outlined, size: 38),
                  Text(
                    notice ?? 'Enter the six-digit code sent to your registered mobile number.',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: code,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    onChanged: (_) => setState(() {}),
                    textAlign: TextAlign.center,
                    decoration: const InputDecoration(
                      labelText: '6-digit verification code',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  FilledButton(
                    onPressed: busy || code.text.trim().length != 6
                        ? null
                        : verifyPhone,
                    child: busy
                        ? const CircularProgressIndicator()
                        : const Text('Verify and continue'),
                  ),
                  TextButton(
                    onPressed: busy ? null : backToLogin,
                    child: const Text('Back to sign in'),
                  ),
                ] else ...[
                  const Icon(Icons.hourglass_top, size: 42),
                  Text(
                    notice ?? 'Your phone is verified. The academy administrator must approve this device.',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: busy ? null : () => checkApproval(),
                    child: busy
                        ? const CircularProgressIndicator()
                        : const Text('Check approval status'),
                  ),
                  const Text(
                    'This screen checks for approval automatically every 15 seconds.',
                    textAlign: TextAlign.center,
                  ),
                  TextButton(
                    onPressed: busy ? null : backToLogin,
                    child: const Text('Cancel'),
                  ),
                ],
                if (error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    error!,
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class StudentHome extends StatefulWidget {
  const StudentHome({required this.api, required this.onLogout, super.key});
  final MobileApi api;
  final VoidCallback onLogout;
  @override
  State<StudentHome> createState() => _StudentHomeState();
}
class _StudentHomeState extends State<StudentHome> {
  final search = TextEditingController();
  List<dynamic> catalog = [];
  List<dynamic> myCourses = [];
  bool loading = true;
  bool loadingMore = false;
  String? error;
  String sort = 'newest';
  int currentPage = 1;
  int lastPage = 1;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool loadMore = false}) async {
    if (loadingMore || (loading && loadMore)) return;
    if (mounted) {
      setState(() {
        if (loadMore) {
          loadingMore = true;
        } else {
          loading = true;
          error = null;
          currentPage = 1;
        }
      });
    }
    try {
      final requestedPage = loadMore ? currentPage + 1 : 1;
      final query = Uri(queryParameters: {
        if (search.text.trim().isNotEmpty) 'q': search.text.trim(),
        'sort': sort,
        'per_page': '12',
        'page': requestedPage.toString(),
      }).query;
      final results = await Future.wait([
        widget.api.get('/courses?$query'),
        if (!loadMore) widget.api.get('/my/courses'),
      ]);
      if (!mounted) { return; }
      final catalogResponse = results[0];
      final nextCourses = catalogResponse['data'] as List<dynamic>? ?? [];
      final meta = catalogResponse['meta'] as Map<String, dynamic>? ?? {};
      setState(() {
        catalog = loadMore ? [...catalog, ...nextCourses] : nextCourses;
        currentPage = (meta['current_page'] as num?)?.toInt() ?? requestedPage;
        lastPage = (meta['last_page'] as num?)?.toInt() ?? currentPage;
        if (!loadMore) {
          myCourses = results[1]['data'] as List<dynamic>? ?? [];
        }
      });
    } catch (exception) {
      if (exception is ApiException && exception.statusCode == 401) {
        widget.onLogout();
      } else if (mounted) {
        setState(() => error = exception.toString());
      }
    } finally {
      if (mounted) {
        setState(() {
          loading = false;
          loadingMore = false;
        });
      }
    }
  }

  Future<void> logout() async {
    await widget.api.logout();
    widget.onLogout();
  }

  Future<void> openAssignments() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => AssignmentsPage(api: widget.api),
    ));
  }

  Future<void> openExams() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => ExamsPage(api: widget.api),
    ));
  }

  Future<void> openWishlist() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => WishlistPage(api: widget.api),
    ));
  }

  Future<void> openCart() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => StudentCartPage(api: widget.api),
    ));
  }

  Future<void> openPayments() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => PaymentHistoryPage(api: widget.api),
    ));
  }

  Future<void> openNotices() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => StudentNoticesPage(api: widget.api),
    ));
  }


  Future<void> openSupport() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => StudentSupportPage(api: widget.api),
    ));
  }
  Future<void> openInvoices() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => StudentInvoicesPage(api: widget.api),
    ));
  }

  Widget _courseCard(dynamic item) {
    final course = item as Map<String, dynamic>;
    final imageUrl = course['image_url']?.toString();
    final instructor = course['instructor'] is Map<String, dynamic>
        ? (course['instructor'] as Map<String, dynamic>)['name']?.toString()
        : null;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: ListTile(
        contentPadding: const EdgeInsets.all(12),
        leading: imageUrl != null && imageUrl.isNotEmpty
            ? ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.network(
                  imageUrl,
                  width: 64,
                  height: 64,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => const SizedBox(
                    width: 64,
                    height: 64,
                    child: Icon(Icons.menu_book, size: 32),
                  ),
                ),
              )
            : const SizedBox(
                width: 64,
                height: 64,
                child: Icon(Icons.menu_book, size: 32),
              ),
        title: Text(
          course['title']?.toString() ?? 'Course',
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (instructor != null && instructor.isNotEmpty) Text(instructor),
            const SizedBox(height: 4),
            Text("৳\${course['price'] ?? 0}"),
          ],
        ),
        isThreeLine: instructor != null && instructor.isNotEmpty,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => CourseDetailsPage(
            api: widget.api,
            slug: course['slug'].toString(),
          ),
        )),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Student Learning'),
      actions: [
        IconButton(
          onPressed: load,
          icon: const Icon(Icons.refresh),
          tooltip: 'Refresh',
        ),
        IconButton(
          onPressed: openAssignments,
          icon: const Icon(Icons.assignment_outlined),
          tooltip: 'My assignments',
        ),
        IconButton(
          onPressed: openExams,
          icon: const Icon(Icons.quiz_outlined),
          tooltip: 'My exams',
        ),
        IconButton(
          onPressed: openCart,
          icon: const Icon(Icons.shopping_cart_outlined),
          tooltip: 'My cart',
        ),
        IconButton(
          onPressed: openWishlist,
          icon: const Icon(Icons.favorite_outline),
          tooltip: 'My wishlist',
        ),
        IconButton(
          onPressed: openSupport,
          icon: const Icon(Icons.support_agent_outlined),
          tooltip: 'Contact support',
        ),
        IconButton(
          onPressed: openNotices,
          icon: const Icon(Icons.campaign_outlined),
          tooltip: 'Notice board',
        ),
        IconButton(
          onPressed: openInvoices,
          icon: const Icon(Icons.request_quote_outlined),
          tooltip: 'My invoices & receipts',
        ),
        IconButton(
          onPressed: openPayments,
          icon: const Icon(Icons.receipt_long_outlined),
          tooltip: 'Payment history',
        ),
        IconButton(
          onPressed: logout,
          icon: const Icon(Icons.logout),
          tooltip: 'Sign out',
        ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: load,
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? ListView(
                  children: [
                    const SizedBox(height: 80),
                    Center(child: Text(error!)),
                    TextButton(onPressed: load, child: const Text('Retry')),
                  ],
                )
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text('My Courses',
                        style: Theme.of(context).textTheme.titleLarge),
                    if (myCourses.isEmpty)
                      const ListTile(title: Text('No active courses yet.')),
                    ...myCourses.map((item) {
                      final enrollment = item as Map<String, dynamic>;
                      final course = enrollment['course'] as Map<String, dynamic>;
                      final progress =
                          enrollment['progress_percent'] as num? ?? 0;
                      return Card(
                        child: ListTile(
                          title: Text(course['title']?.toString() ?? 'Course'),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('$progress% completed'),
                              const SizedBox(height: 6),
                              LinearProgressIndicator(
                                value: progress.toDouble().clamp(0, 100) / 100,
                              ),
                            ],
                          ),
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => CourseDetailsPage(
                                api: widget.api,
                                slug: course['slug'].toString(),
                              ),
                            ),
                          ),
                        ),
                      );
                    }),
                    const SizedBox(height: 20),
                    Text('Explore Courses',
                        style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 12),
                    TextField(
                      controller: search,
                      textInputAction: TextInputAction.search,
                      onSubmitted: (_) => load(),
                      decoration: InputDecoration(
                        labelText: 'Search courses or instructors',
                        prefixIcon: const Icon(Icons.search),
                        suffixIcon: IconButton(
                          tooltip: 'Clear search',
                          onPressed: () {
                            search.clear();
                            load();
                          },
                          icon: const Icon(Icons.clear),
                        ),
                        border: const OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      initialValue: sort,
                      decoration: const InputDecoration(
                        labelText: 'Sort courses',
                        border: OutlineInputBorder(),
                      ),
                      items: const [
                        DropdownMenuItem(
                            value: 'newest', child: Text('Newest first')),
                        DropdownMenuItem(
                            value: 'oldest', child: Text('Oldest first')),
                        DropdownMenuItem(
                            value: 'price_low',
                            child: Text('Price: low to high')),
                        DropdownMenuItem(
                            value: 'price_high',
                            child: Text('Price: high to low')),
                        DropdownMenuItem(
                            value: 'title', child: Text('Course title')),
                      ],
                      onChanged: (value) {
                        if (value == null) return;
                        setState(() => sort = value);
                        load();
                      },
                    ),
                    const SizedBox(height: 12),
                    if (catalog.isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(20),
                        child: Center(
                          child: Text('No courses match your search.'),
                        ),
                      ),
                    ...catalog.map(_courseCard),
                    if (currentPage < lastPage)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: OutlinedButton(
                          onPressed:
                              loadingMore ? null : () => load(loadMore: true),
                          child: loadingMore
                              ? const CircularProgressIndicator()
                              : const Text('Load more courses'),
                        ),
                      ),
                  ],
                ),
    ),
  );
}

class WishlistPage extends StatefulWidget {
  const WishlistPage({required this.api, super.key});
  final MobileApi api;

  @override
  State<WishlistPage> createState() => _WishlistPageState();
}

class _WishlistPageState extends State<WishlistPage> {
  List<dynamic> courses = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.api.get('/my/wishlist');
      if (!mounted) { return; }
      setState(() => courses = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> removeCourse(Map<String, dynamic> course) async {
    try {
      await widget.api.delete('/courses/${course['slug']}/wishlist');
      if (mounted) {
        setState(() {
          courses.removeWhere(
            (item) => (item as Map<String, dynamic>)['id'] == course['id'],
          );
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Course removed from wishlist.')),
        );
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(exception.toString())),
        );
      }
    }
  }

  Future<void> openCourse(String slug) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => CourseDetailsPage(api: widget.api, slug: slug),
    ));
    if (mounted) load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('My wishlist'),
          actions: [
            IconButton(
              onPressed: load,
              tooltip: 'Refresh wishlist',
              icon: const Icon(Icons.refresh),
            ),
          ],
        ),
        body: loading
            ? const Center(child: CircularProgressIndicator())
            : error != null
                ? Center(child: Text(error!))
                : courses.isEmpty
                    ? const Center(child: Text('Your wishlist is empty.'))
                    : RefreshIndicator(
                        onRefresh: load,
                        child: ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: courses.length,
                          itemBuilder: (context, index) {
                            final course = courses[index] as Map<String, dynamic>;
                            final instructor =
                                course['instructor'] as Map<String, dynamic>?;
                            return Card(
                              child: ListTile(
                                leading: const Icon(Icons.menu_book),
                                title: Text(course['title']?.toString() ?? 'Course'),
                                subtitle: Text(
                                  instructor?['name']?.toString() ?? 'Academy course',
                                ),
                                onTap: () => openCourse(course['slug'].toString()),
                                trailing: IconButton(
                                  tooltip: 'Remove from wishlist',
                                  onPressed: () => removeCourse(course),
                                  icon: const Icon(Icons.favorite, color: Colors.red),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
      );
}

class StudentSupportPage extends StatefulWidget {
  const StudentSupportPage({required this.api, super.key});
  final MobileApi api;

  @override
  State<StudentSupportPage> createState() => _StudentSupportPageState();
}

class _StudentSupportPageState extends State<StudentSupportPage> {
  List<dynamic> tickets = [];
  bool loading = true;
  bool creating = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) { setState(() { loading = true; error = null; }); }
    try {
      final response = await widget.api.get('/my/support-tickets');
      if (!mounted) { return; }
      setState(() => tickets = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> createTicket() async {
    final subject = TextEditingController();
    final message = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('New support request'),
        content: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(
              controller: subject,
              maxLength: 160,
              decoration: const InputDecoration(labelText: 'Subject', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: message,
              minLines: 4,
              maxLines: 7,
              maxLength: 5000,
              decoration: const InputDecoration(labelText: 'Describe your question or issue', border: OutlineInputBorder()),
            ),
          ]),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
    if (submitted != true) {
      subject.dispose();
      message.dispose();
      return;
    }
    if (subject.text.trim().length < 5 || message.text.trim().length < 5) {
      setState(() => error = 'Please enter a subject and a message of at least five characters.');
      subject.dispose();
      message.dispose();
      return;
    }
    setState(() { creating = true; error = null; });
    try {
      final response = await widget.api.postJson('/my/support-tickets', {
        'subject': subject.text.trim(),
        'message': message.text.trim(),
      });
      await load();
      if (!mounted) return;
      final data = response['data'] as Map<String, dynamic>;
      await openTicket(data);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      subject.dispose();
      message.dispose();
      if (mounted) { setState(() => creating = false); }
    }
  }

  Future<void> openTicket(Map<String, dynamic> ticket) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => SupportTicketConversationPage(
        api: widget.api,
        ticketId: (ticket['id'] as num).toInt(),
      ),
    ));
    await load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Student Support'),
      actions: [
        IconButton(onPressed: loading ? null : load, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
      ],
    ),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: creating ? null : createTicket,
      icon: const Icon(Icons.add),
      label: const Text('New request'),
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null && tickets.isEmpty
            ? Center(child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text(error!),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: load, child: const Text('Retry')),
                ]),
              ))
            : RefreshIndicator(
                onRefresh: load,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                  children: [
                    const Text('Ask the academy a question or report a problem. Replies from the support team will appear in your ticket.'),
                    const SizedBox(height: 12),
                    if (tickets.isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(32),
                        child: Center(child: Text('You have not opened a support request yet.')),
                      ),
                    ...tickets.map((item) {
                      final ticket = item as Map<String, dynamic>;
                      final status = ticket['status']?.toString() ?? 'open';
                      return Card(
                        child: ListTile(
                          leading: const Icon(Icons.support_agent),
                          title: Text(ticket['subject']?.toString() ?? 'Support request'),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(ticket['last_message']?.toString() ?? ''),
                              const SizedBox(height: 4),
                              Text('Ticket #${ticket['id']} · ${status.replaceAll('_', ' ')}'),
                            ],
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => openTicket(ticket),
                        ),
                      );
                    }),
                  ],
                ),
              ),
  );
}

class SupportTicketConversationPage extends StatefulWidget {
  const SupportTicketConversationPage({required this.api, required this.ticketId, super.key});
  final MobileApi api;
  final int ticketId;

  @override
  State<SupportTicketConversationPage> createState() => _SupportTicketConversationPageState();
}

class _SupportTicketConversationPageState extends State<SupportTicketConversationPage> {
  final reply = TextEditingController();
  Map<String, dynamic>? ticket;
  bool loading = true;
  bool sending = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    reply.dispose();
    super.dispose();
  }

  Future<void> load() async {
    if (mounted) setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/my/support-tickets/${widget.ticketId}');
      if (!mounted) return;
      setState(() => ticket = response['data'] as Map<String, dynamic>);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> sendReply() async {
    if (sending || reply.text.trim().length < 2) { return; }
    setState(() { sending = true; error = null; });
    try {
      await widget.api.postJson('/my/support-tickets/${widget.ticketId}/messages', {'message': reply.text.trim()});
      reply.clear();
      await load();
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) { setState(() => sending = false); }
    }
  }

  @override
  Widget build(BuildContext context) {
    final messages = ticket?['messages'] as List<dynamic>? ?? [];
    final closed = ticket?['status'] == 'closed';
    return Scaffold(
      appBar: AppBar(title: Text(ticket?['subject']?.toString() ?? 'Support ticket')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null && ticket == null
              ? Center(child: Text(error!))
              : Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: [
                          Expanded(child: Text('Ticket #${widget.ticketId} · ${ticket?['status'] ?? 'open'}')),
                          IconButton(onPressed: load, icon: const Icon(Icons.refresh), tooltip: 'Refresh conversation'),
                        ],
                      ),
                    ),
                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.all(12),
                        children: messages.map((item) {
                          final message = item as Map<String, dynamic>;
                          final fromAcademy = message['sender_role'] == 'admin';
                          return Align(
                            alignment: fromAcademy ? Alignment.centerLeft : Alignment.centerRight,
                            child: Container(
                              constraints: const BoxConstraints(maxWidth: 340),
                              margin: const EdgeInsets.symmetric(vertical: 5),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: fromAcademy ? Theme.of(context).colorScheme.surfaceContainerHighest : Theme.of(context).colorScheme.primaryContainer,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(message['sender_name']?.toString() ?? 'Support', style: const TextStyle(fontWeight: FontWeight.bold)),
                                  const SizedBox(height: 4),
                                  Text(message['body']?.toString() ?? ''),
                                ],
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    ),
                    if (error != null)
                      Padding(padding: const EdgeInsets.all(8), child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
                    if (closed)
                      const Padding(padding: EdgeInsets.all(16), child: Text('This ticket is closed. Open a new request if you need more help.'))
                    else
                      SafeArea(
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Row(
                            children: [
                              Expanded(
                                child: TextField(
                                  controller: reply,
                                  minLines: 1,
                                  maxLines: 4,
                                  maxLength: 5000,
                                  decoration: const InputDecoration(hintText: 'Write a reply...', border: OutlineInputBorder()),
                                ),
                              ),
                              const SizedBox(width: 8),
                              IconButton.filled(
                                onPressed: sending ? null : sendReply,
                                icon: sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.send),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
    );
  }
}

class CourseDetailsPage extends StatefulWidget {
  const CourseDetailsPage({required this.api, required this.slug, super.key});
  final MobileApi api;
  final String slug;

  @override
  State<CourseDetailsPage> createState() => _CourseDetailsPageState();
}

class _CourseDetailsPageState extends State<CourseDetailsPage> {
  Map<String, dynamic>? course;
  List<dynamic> lessons = [];
  bool enrolled = false;
  bool wishlisted = false;
  bool wishlistBusy = false;
  bool cartBusy = false;
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.api.get('/courses/${widget.slug}');
      if (!mounted) {
        return;
      }
      setState(() {
        course = response['course'] as Map<String, dynamic>;
        lessons = response['lessons'] as List<dynamic>? ?? [];
        enrolled = response['is_enrolled'] == true;
        wishlisted = response['is_wishlisted'] == true;
      });
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> addToCart() async {
    final item = course;
    if (item == null || cartBusy) return;
    setState(() => cartBusy = true);
    try {
      await widget.api.postJson('/my/cart', {'course_id': (item['id'] as num).toInt()});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Course saved in your cart.')),
        );
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => cartBusy = false);
    }
  }

  Future<void> toggleWishlist() async {
    if (course == null || wishlistBusy) return;
    setState(() => wishlistBusy = true);
    try {
      final response = wishlisted
          ? await widget.api.delete('/courses/${widget.slug}/wishlist')
          : await widget.api.postJson(
              '/courses/${widget.slug}/wishlist',
              const {},
            );
      if (mounted) {
        setState(() => wishlisted = response['wishlisted'] == true);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              wishlisted ? 'Added to your wishlist.' : 'Removed from your wishlist.',
            ),
          ),
        );
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(exception.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => wishlistBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Course details'),
          actions: [
            IconButton(
              onPressed: wishlistBusy ? null : toggleWishlist,
              tooltip: wishlisted ? 'Remove from wishlist' : 'Add to wishlist',
              icon: Icon(
                wishlisted ? Icons.favorite : Icons.favorite_outline,
                color: wishlisted ? Colors.red : null,
              ),
            ),
          ],
        ),
        body: loading
            ? const Center(child: CircularProgressIndicator())
            : error != null
                ? Center(child: Text(error!))
                : RefreshIndicator(
                    onRefresh: load,
                    child: ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Text(
                          course?['title']?.toString() ?? 'Course',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 8),
                        Text(course?['description']?.toString() ?? ''),
                        if (!enrolled) ...[
                          const SizedBox(height: 16),
                          OutlinedButton.icon(
                            onPressed: cartBusy ? null : addToCart,
                            icon: const Icon(Icons.add_shopping_cart),
                            label: Text(cartBusy ? 'Adding…' : 'Add to cart'),
                          ),
                          FilledButton.icon(
                            onPressed: () async {
                              final completed = await Navigator.of(context).push<bool>(
                                MaterialPageRoute(
                                  builder: (_) => CourseCheckoutPage(api: widget.api, slug: widget.slug),
                                ),
                              );
                              if (completed == true && mounted) { await load(); }
                            },
                            icon: const Icon(Icons.shopping_cart_checkout),
                            label: const Text('Enroll / Purchase course'),
                          ),
                        ],
                        const SizedBox(height: 20),
                        Text('Course curriculum', style: Theme.of(context).textTheme.titleLarge),
                        if (lessons.isEmpty)
                          const ListTile(title: Text('No preview lessons are available.')),
                        const SizedBox(height: 24),
                        Text('Student reviews', style: Theme.of(context).textTheme.titleLarge),
                        CourseReviewsPanel(api: widget.api, slug: widget.slug, canReview: enrolled),
                        ...lessons.map((item) {
                          final lesson = item as Map<String, dynamic>;
                          return Card(
                            child: ListTile(
                              leading: const Icon(Icons.play_circle_outline),
                              title: Text(lesson['title']?.toString() ?? 'Lesson'),
                              subtitle: Text(
                                '${lesson['duration_minutes'] ?? 0} minutes',
                              ),
                              trailing: lesson['is_preview'] == true
                                  ? const Chip(label: Text('Preview'))
                                  : null,
                              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                                builder: (_) => LessonPlayerPage(
                                  api: widget.api,
                                  slug: widget.slug,
                                  lessonId: lesson['id'].toString(),
                                  enrolled: enrolled,
                                ),
                              )),
                            ),
                          );
                        }),
                      ],
                    ),
                  ),
      );
}

class StudentCartPage extends StatefulWidget {
  const StudentCartPage({required this.api, super.key});
  final MobileApi api;

  @override
  State<StudentCartPage> createState() => _StudentCartPageState();
}

class _StudentCartPageState extends State<StudentCartPage> {
  final couponController = TextEditingController();
  final transactionReferenceController = TextEditingController();
  final senderAccountController = TextEditingController();
  List<dynamic> items = [];
  List<dynamic> paymentMethods = [];
  Map<String, dynamic> totals = {};
  Map<String, dynamic> couponPricing = {};
  bool loading = true;
  bool applyingCoupon = false;
  bool submitting = false;
  String? error;
  String? selectedPaymentMethod;

  Map<String, dynamic>? get selectedMethodData {
    for (final raw in paymentMethods) {
      final method = raw as Map<String, dynamic>;
      if (method['key']?.toString() == selectedPaymentMethod) {
        return method;
      }
    }
    return null;
  }

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    couponController.dispose();
    transactionReferenceController.dispose();
    senderAccountController.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() { loading = true; error = null; couponPricing = {}; });
    try {
      final response = await widget.api.get('/my/cart');
      final options = await widget.api.get('/my/cart/checkout/options');
      if (!mounted) { return; }
      setState(() {
        items = response['data'] as List<dynamic>? ?? [];
        totals = response['meta'] as Map<String, dynamic>? ?? {};
        paymentMethods = (options['data'] as Map<String, dynamic>?)?['payment_methods'] as List<dynamic>? ?? [];
        final availableMethodKeys = paymentMethods
            .map((raw) => (raw as Map<String, dynamic>)['key']?.toString())
            .whereType<String>()
            .toSet();
        if (!availableMethodKeys.contains(selectedPaymentMethod)) {
          selectedPaymentMethod = paymentMethods.isEmpty
              ? null
              : (paymentMethods.first as Map<String, dynamic>)['key']?.toString();
        }
        if (items.isEmpty) {
          couponPricing = {};
          couponController.clear();
          transactionReferenceController.clear();
          senderAccountController.clear();
        }
      });
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> applyCoupon() async {
    if (couponController.text.trim().isEmpty) { return; }
    setState(() => applyingCoupon = true);
    try {
      final response = await widget.api.postJson('/my/cart/coupon', {
        'code': couponController.text.trim(),
      });
      if (!mounted) { return; }
      setState(() => couponPricing = response['data'] as Map<String, dynamic>? ?? {});
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Coupon applied to this cart.')));
    } catch (exception) {
      if (mounted) {
        setState(() => couponPricing = {});
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) { setState(() => applyingCoupon = false); }
    }
  }

  Future<void> remove(Map<String, dynamic> item) async {
    final course = item['course'] as Map<String, dynamic>;
    try {
      await widget.api.delete('/my/cart/${course['slug']}');
      if (mounted) {
        setState(() => couponPricing = {});
        await load();
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    }
  }

  Future<void> clear() async {
    try {
      await widget.api.delete('/my/cart');
      if (mounted) {
        setState(() => couponPricing = {});
        await load();
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    }
  }

  Future<void> checkout() async {
    final estimatedTotal = double.tryParse((couponPricing['total'] ?? totals['total'] ?? '0').toString()) ?? 0;
    if (estimatedTotal > 0 && (selectedPaymentMethod == null || transactionReferenceController.text.trim().isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select a payment method and enter the transaction reference.')));
      return;
    }
    setState(() => submitting = true);
    try {
      final payload = <String, dynamic>{
        'transaction_reference': transactionReferenceController.text.trim(),
        'sender_account': senderAccountController.text.trim(),
      };
      if (selectedPaymentMethod != null) { payload['payment_method'] = selectedPaymentMethod; }
      if (couponPricing.isNotEmpty) { payload['coupon_code'] = couponController.text.trim(); }
      final response = await widget.api.postJson('/my/cart/checkout/manual', payload);
      if (!mounted) { return; }
      final data = response['data'] as Map<String, dynamic>? ?? {};
      final message = data['status'] == 'enrolled_free'
          ? 'Your cart courses are now enrolled.'
          : 'Payment submitted. Your courses will unlock after payment approval.';
      transactionReferenceController.clear();
      senderAccountController.clear();
      couponController.clear();
      setState(() { couponPricing = {}; });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      await load();
    } catch (exception) {
      if (mounted) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString()))); }
    } finally {
      if (mounted) { setState(() => submitting = false); }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('My cart'),
      actions: [
        if (items.isNotEmpty)
          IconButton(onPressed: clear, tooltip: 'Clear cart', icon: const Icon(Icons.delete_sweep_outlined)),
        IconButton(onPressed: load, tooltip: 'Refresh cart', icon: const Icon(Icons.refresh)),
      ],
    ),
    body: loading
      ? const Center(child: CircularProgressIndicator())
      : error != null
        ? Center(child: Text(error!))
        : items.isEmpty
          ? const Center(child: Text('Your cart is empty. Add courses to keep them here.'))
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  ...items.map((raw) {
                    final item = raw as Map<String, dynamic>;
                    final course = item['course'] as Map<String, dynamic>;
                    final pricing = item['pricing'] as Map<String, dynamic>;
                    final batch = item['batch'] as Map<String, dynamic>?;
                    return Card(
                      child: ListTile(
                        title: Text(course['title']?.toString() ?? 'Course'),
                        subtitle: Text([
                          if (batch != null) 'Batch: ${batch['title']}',
                          '৳${pricing['total']}',
                          if ((pricing['discount_amount']?.toString() ?? '0.00') != '0.00')
                            'Discount: ৳${pricing['discount_amount']}',
                        ].join(' • ')),
                        trailing: IconButton(
                          tooltip: 'Remove course',
                          onPressed: () => remove(item),
                          icon: const Icon(Icons.delete_outline),
                        ),
                      ),
                    );
                  }),
                  const SizedBox(height: 12),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text('Items: ${totals['items_count'] ?? items.length}'),
                          Text('Subtotal: ৳${totals['subtotal'] ?? '0.00'}'),
                          Text('Flash sale discount: ৳${totals['discount_total'] ?? '0.00'}'),
                          const SizedBox(height: 12),
                          TextField(
                            controller: couponController,
                            textCapitalization: TextCapitalization.characters,
                            decoration: InputDecoration(
                              labelText: 'Coupon code',
                              suffixIcon: IconButton(
                                onPressed: applyingCoupon ? null : applyCoupon,
                                icon: applyingCoupon
                                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                    : const Icon(Icons.check_circle_outline),
                              ),
                            ),
                          ),
                          if (couponPricing.isNotEmpty) ...[
                            const SizedBox(height: 8),
                            Text('Coupon ${couponPricing['code']} applied: −৳${couponPricing['discount']}'),
                          ],
                          const Divider(height: 24),
                          Text('Payable: ৳${couponPricing['total'] ?? totals['total'] ?? '0.00'}',
                            style: Theme.of(context).textTheme.titleLarge),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            initialValue: selectedPaymentMethod,
                            decoration: const InputDecoration(labelText: 'Payment method'),
                            items: paymentMethods.map((raw) {
                              final method = raw as Map<String, dynamic>;
                              return DropdownMenuItem<String>(
                                value: method['key']?.toString(),
                                child: Text(method['label']?.toString() ?? method['key'].toString()),
                              );
                            }).toList(),
                            onChanged: (value) => setState(() => selectedPaymentMethod = value),
                          ),
                          if (selectedMethodData != null) ...[
                            const SizedBox(height: 6),
                            Text([
                              if ((selectedMethodData!['account']?.toString() ?? '').isNotEmpty)
                                'Academy account: ${selectedMethodData!['account']}',
                              if ((selectedMethodData!['instructions']?.toString() ?? '').isNotEmpty)
                                selectedMethodData!['instructions'].toString(),
                            ].join(' • ')),
                          ],
                          const SizedBox(height: 8),
                          TextField(
                            controller: senderAccountController,
                            decoration: const InputDecoration(labelText: 'Your sender account (optional)'),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: transactionReferenceController,
                            decoration: const InputDecoration(labelText: 'Transaction reference'),
                          ),
                          const SizedBox(height: 16),
                          FilledButton.icon(
                            onPressed: submitting ? null : checkout,
                            icon: submitting
                                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                : const Icon(Icons.lock_outline),
                            label: Text(submitting ? 'Submitting…' : 'Submit cart checkout'),
                          ),
                          const SizedBox(height: 8),
                          const Text('Payment is reviewed by the academy. Course access is granted after approval. Coupon discounts cannot be combined with Flash Sales.'),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
  );
}

class StudentInvoicesPage extends StatefulWidget {
  const StudentInvoicesPage({required this.api, super.key});
  final MobileApi api;

  @override
  State<StudentInvoicesPage> createState() => _StudentInvoicesPageState();
}

class _StudentInvoicesPageState extends State<StudentInvoicesPage> {
  List<dynamic> invoices = [];
  bool loading = true;
  bool loadingMore = false;
  String? error;
  int currentPage = 1;
  int lastPage = 1;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load({bool append = false}) async {
    if (append && (loadingMore || currentPage >= lastPage)) { return; }
    setState(() {
      if (append) {
        loadingMore = true;
      } else {
        loading = true;
        error = null;
      }
    });
    try {
      final nextPage = append ? currentPage + 1 : 1;
      final response = await widget.api.get('/my/invoices?page=$nextPage');
      if (!mounted) { return; }
      final pageItems = response['data'] as List<dynamic>? ?? [];
      final meta = response['meta'] as Map<String, dynamic>? ?? {};
      setState(() {
        if (append) {
          invoices.addAll(pageItems);
        } else {
          invoices = pageItems;
        }
        currentPage = (meta['current_page'] as num?)?.toInt() ?? nextPage;
        lastPage = (meta['last_page'] as num?)?.toInt() ?? currentPage;
      });
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) {
        setState(() {
          loading = false;
          loadingMore = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('My invoices & receipts'), actions: [
      IconButton(onPressed: () => load(), icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
    ]),
    body: loading
      ? const Center(child: CircularProgressIndicator())
      : error != null
        ? Center(child: Text(error!))
        : invoices.isEmpty
          ? const Center(child: Text('No invoices have been issued to your account yet.'))
          : RefreshIndicator(
              onRefresh: () => load(),
              child: ListView.builder(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                itemCount: invoices.length + (currentPage < lastPage ? 1 : 0),
                itemBuilder: (context, index) {
                  if (index == invoices.length) {
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      child: Center(
                        child: TextButton.icon(
                          onPressed: loadingMore ? null : () => load(append: true),
                          icon: loadingMore
                              ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Icon(Icons.expand_more),
                          label: Text(loadingMore ? 'Loading…' : 'Load more invoices'),
                        ),
                      ),
                    );
                  }
                  final invoice = invoices[index] as Map<String, dynamic>;
                  final course = invoice['course'] as Map<String, dynamic>?;
                  return Card(
                    child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.receipt_long_outlined)),
                      title: Text(invoice['title']?.toString() ?? 'Invoice'),
                      subtitle: Text([
                        invoice['invoice_number']?.toString() ?? '',
                        if (course != null) course['title']?.toString() ?? '',
                        'Paid ৳${invoice['net_paid'] ?? '0.00'}',
                        'Due ৳${invoice['outstanding'] ?? invoice['amount'] ?? '0.00'}',
                      ].where((value) => value.isNotEmpty).join(' • ')),
                      trailing: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(invoice['status']?.toString().toUpperCase() ?? 'UNPAID',
                            style: Theme.of(context).textTheme.labelSmall),
                          const Icon(Icons.chevron_right),
                        ],
                      ),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => StudentInvoiceDetailsPage(
                          api: widget.api,
                          invoiceId: (invoice['id'] as num).toInt(),
                        ),
                      )),
                    ),
                  );
                },
              ),
            ),
  );
}

class StudentInvoiceDetailsPage extends StatefulWidget {
  const StudentInvoiceDetailsPage({
    required this.api,
    required this.invoiceId,
    super.key,
  });
  final MobileApi api;
  final int invoiceId;

  @override
  State<StudentInvoiceDetailsPage> createState() => _StudentInvoiceDetailsPageState();
}

class _StudentInvoiceDetailsPageState extends State<StudentInvoiceDetailsPage> {
  Map<String, dynamic>? invoice;
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/my/invoices/${widget.invoiceId}');
      if (!mounted) { return; }
      setState(() => invoice = response['data'] as Map<String, dynamic>?);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> openPrintable(String? url) async {
    if (url == null || url.isEmpty) { return; }
    final uri = Uri.tryParse(url);
    if (uri == null || uri.scheme != 'https') {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('The print link is invalid.')));
      return;
    }
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Could not open the print view.')));
    }
  }

  String money(dynamic value) => '৳${value?.toString() ?? '0.00'}';

  @override
  Widget build(BuildContext context) {
    final data = invoice;
    final payments = data?['payments'] as List<dynamic>? ?? [];
    return Scaffold(
      appBar: AppBar(title: const Text('Invoice details'), actions: [
        IconButton(onPressed: load, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
      ]),
      body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
          ? Center(child: Text(error!))
          : data == null
            ? const Center(child: Text('Invoice not found.'))
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Card(child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Expanded(child: Text(data['title']?.toString() ?? 'Invoice',
                          style: Theme.of(context).textTheme.titleLarge)),
                        Chip(label: Text(data['status']?.toString().toUpperCase() ?? 'UNPAID')),
                      ]),
                      Text(data['invoice_number']?.toString() ?? ''),
                      if (data['course'] is Map<String, dynamic>)
                        Text('Course: ${(data['course'] as Map<String, dynamic>)['title']}'),
                      if (data['batch'] is Map<String, dynamic>)
                        Text('Batch: ${(data['batch'] as Map<String, dynamic>)['title']}'),
                      if (data['due_date'] != null) Text('Due date: ${data['due_date']}'),
                      if ((data['description'] ?? '').toString().isNotEmpty)
                        Padding(padding: const EdgeInsets.only(top: 8), child: Text(data['description'].toString())),
                      const Divider(height: 28),
                      _invoiceLine('Subtotal', money(data['subtotal'])),
                      _invoiceLine('Discount', money(((double.tryParse(data['subtotal'].toString()) ?? 0) - (double.tryParse(data['amount'].toString()) ?? 0)).toStringAsFixed(2))),
                      _invoiceLine('Invoice total', money(data['amount']), bold: true),
                      _invoiceLine('Gross paid', money(data['gross_paid'])),
                      _invoiceLine('Refunded', money(data['refunded'])),
                      _invoiceLine('Net paid', money(data['net_paid'])),
                      _invoiceLine('Outstanding', money(data['outstanding']), bold: true),
                      const SizedBox(height: 16),
                      SizedBox(width: double.infinity, child: OutlinedButton.icon(
                        onPressed: () => openPrintable(data['print_url']?.toString()),
                        icon: const Icon(Icons.picture_as_pdf_outlined),
                        label: const Text('Print / save invoice as PDF'),
                      )),
                    ]),
                  )),
                  const SizedBox(height: 12),
                  Text('Payment receipts', style: Theme.of(context).textTheme.titleLarge),
                  if (payments.isEmpty)
                    const Card(child: Padding(padding: EdgeInsets.all(16), child: Text('No payments recorded yet.')))
                  else
                    ...payments.map((raw) {
                      final payment = raw as Map<String, dynamic>;
                      return Card(child: ListTile(
                        leading: const Icon(Icons.verified_outlined),
                        title: Text(money(payment['amount'])),
                        subtitle: Text([
                          payment['method']?.toString() ?? '',
                          payment['paid_at']?.toString() ?? '',
                          if ((payment['reference'] ?? '').toString().isNotEmpty)
                            'Ref: ${payment['reference']}',
                        ].where((value) => value.isNotEmpty).join(' • ')),
                        trailing: IconButton(
                          tooltip: 'Print receipt',
                          onPressed: () => openPrintable(payment['print_url']?.toString()),
                          icon: const Icon(Icons.print_outlined),
                        ),
                      ));
                    }),
                ],
              ),
    );
  }

  Widget _invoiceLine(String label, String value, {bool bold = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(children: [
      Expanded(child: Text(label, style: bold ? const TextStyle(fontWeight: FontWeight.bold) : null)),
      Text(value, style: bold ? const TextStyle(fontWeight: FontWeight.bold) : null),
    ]),
  );
}

class CourseCheckoutPage extends StatefulWidget {
  const CourseCheckoutPage({required this.api, required this.slug, super.key});
  final MobileApi api;
  final String slug;
  @override
  State<CourseCheckoutPage> createState() => _CourseCheckoutPageState();
}

class _CourseCheckoutPageState extends State<CourseCheckoutPage> {
  final transactionReference = TextEditingController();
  final senderAccount = TextEditingController();
  Map<String, dynamic>? checkout;
  bool loading = true;
  bool submitting = false;
  String? error;
  int? selectedBatchId;
  String? selectedMethod;
  PlatformFile? proofFile;

  @override
  void initState() { super.initState(); load(); }

  @override
  void dispose() {
    transactionReference.dispose();
    senderAccount.dispose();
    super.dispose();
  }

  List<dynamic> get batches => checkout?['batches'] as List<dynamic>? ?? [];
  List<dynamic> get methods => checkout?['payment_methods'] as List<dynamic>? ?? [];

  Map<String, dynamic>? get selectedBatch {
    for (final item in batches) {
      final batch = item as Map<String, dynamic>;
      if (batch['id'] == selectedBatchId) { return batch; }
    }
    return null;
  }

  Map<String, dynamic>? get selectedMethodData {
    for (final item in methods) {
      final method = item as Map<String, dynamic>;
      if (method['key'] == selectedMethod) { return method; }
    }
    return null;
  }

  double get payableAmount {
    final batch = selectedBatch;
    final pricing = checkout?['pricing'] as Map<String, dynamic>? ?? {};
    return double.tryParse((batch?['discounted_amount'] ?? pricing['discounted_amount'] ?? 0).toString()) ?? 0;
  }

  bool get selectedBatchFull =>
      selectedBatch != null && ((selectedBatch!['available_seats'] as num?)?.toInt() ?? 0) <= 0;

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/checkout/courses/${widget.slug}');
      if (!mounted) { return; }
      final data = response['data'] as Map<String, dynamic>;
      final nextBatches = data['batches'] as List<dynamic>? ?? [];
      final nextMethods = data['payment_methods'] as List<dynamic>? ?? [];
      setState(() {
        checkout = data;
        if (selectedBatchId == null && nextBatches.length == 1) {
          selectedBatchId = (nextBatches.first as Map<String, dynamic>)['id'] as int;
        }
        if (selectedMethod == null && nextMethods.isNotEmpty) {
          selectedMethod = (nextMethods.first as Map<String, dynamic>)['key']?.toString();
        }
      });
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> pickProof() async {
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
    );
    if (!mounted || file == null) { return; }
    final bytes = await file.readAsBytes();
    if (!mounted) { return; }
    if (bytes.lengthInBytes > 5 * 1024 * 1024) {
      setState(() => error = 'Payment proof must be 5 MB or smaller.');
      return;
    }
    setState(() {
      proofFile = file;
      error = null;
    });
  }

  Future<void> submitManual() async {
    if (submitting || checkout == null || checkout!['already_enrolled'] == true || checkout!['pending_payment'] != null) { return; }
    if (batches.isNotEmpty && selectedBatchId == null) {
      setState(() => error = 'Please select an available batch.');
      return;
    }
    if (selectedBatchFull) {
      setState(() => error = 'This batch is full. Please select another batch.');
      return;
    }
    if (payableAmount > 0 && (selectedMethod == null || transactionReference.text.trim().isEmpty)) {
      setState(() => error = 'Choose a payment method and enter its transaction reference.');
      return;
    }
    setState(() { submitting = true; error = null; });
    try {
      final payload = <String, dynamic>{
        if (selectedBatchId != null) 'batch_id': selectedBatchId,
        if (payableAmount > 0) 'payment_method': selectedMethod,
        if (payableAmount > 0) 'transaction_reference': transactionReference.text.trim(),
        if (senderAccount.text.trim().isNotEmpty) 'sender_account': senderAccount.text.trim(),
      };
      final fields = payload.map((key, value) => MapEntry(key, value.toString()));
      final response = proofFile == null
          ? await widget.api.postJson('/checkout/courses/${widget.slug}/manual', payload)
          : await widget.api.postMultipart(
              '/checkout/courses/${widget.slug}/manual',
              fields,
              attachment: proofFile,
              fileField: 'proof',
            );
      if (!mounted) { return; }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response['message']?.toString() ?? 'Checkout submitted.')),
      );
      proofFile = null;
      Navigator.of(context).pop(true);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => submitting = false); }
    }
  }

  Future<void> payWithBkash() async {
    if (submitting || checkout == null || checkout!['already_enrolled'] == true || checkout!['pending_payment'] != null) { return; }
    if (batches.isNotEmpty && selectedBatchId == null) {
      setState(() => error = 'Please select a batch first.');
      return;
    }
    if (selectedBatchFull) {
      setState(() => error = 'This batch is full. Please select another batch.');
      return;
    }
    setState(() { submitting = true; error = null; });
    try {
      final response = await widget.api.postJson(
        '/checkout/courses/${widget.slug}/bkash',
        {if (selectedBatchId != null) 'batch_id': selectedBatchId},
      );
      final data = response['data'] as Map<String, dynamic>;
      final uri = Uri.tryParse(data['checkout_url']?.toString() ?? '');
      const allowedHosts = {
        'tokenized.sandbox.bka.sh',
        'tokenized.pay.bka.sh',
        'checkout.sandbox.bka.sh',
        'checkout.pay.bka.sh',
      };
      if (uri == null || uri.scheme != 'https' || !allowedHosts.contains(uri.host)) {
        throw const FormatException('The bKash checkout link is invalid.');
      }
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened) { throw const FormatException('Could not open the bKash checkout page.'); }
      if (!mounted) { return; }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Complete payment in bKash, then return and refresh your course status.')),
      );
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => submitting = false); }
    }
  }

  @override
  Widget build(BuildContext context) {
    final course = checkout?['course'] as Map<String, dynamic>? ?? {};
    final manualMethods = methods
        .cast<Map<String, dynamic>>()
        .where((method) => method['key'] != 'bkash')
        .toList();
    final onlineBkash = checkout?['online_bkash_available'] == true;
    final pendingPayment = checkout?['pending_payment'] as Map<String, dynamic>?;
    return Scaffold(
      appBar: AppBar(title: const Text('Course checkout')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null && checkout == null
              ? Center(child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Text(error!),
                    const SizedBox(height: 12),
                    FilledButton(onPressed: load, child: const Text('Retry')),
                  ]),
                ))
              : RefreshIndicator(
                  onRefresh: load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(course['title']?.toString() ?? 'Course',
                          style: Theme.of(context).textTheme.headlineSmall),
                      if (checkout?['already_enrolled'] == true)
                        const Card(child: ListTile(
                          leading: Icon(Icons.check_circle, color: Colors.green),
                          title: Text('You are already enrolled in this course.'),
                        )),
                      if (pendingPayment != null)
                        Card(child: ListTile(
                          leading: const Icon(Icons.hourglass_top),
                          title: const Text('Payment awaiting academy verification'),
                          subtitle: Text('৳${pendingPayment['amount']} · ${pendingPayment['method']} · ${pendingPayment['transaction_reference']}'),
                        )),
                      if (batches.isNotEmpty) ...[
                        const SizedBox(height: 16),
                        DropdownButtonFormField<int>(
                          initialValue: selectedBatchId,
                          decoration: const InputDecoration(labelText: 'Select batch', border: OutlineInputBorder()),
                          items: batches.map((item) {
                            final batch = item as Map<String, dynamic>;
                            final seats = (batch['available_seats'] as num?)?.toInt() ?? 0;
                            return DropdownMenuItem<int>(
                              value: batch['id'] as int,
                              enabled: seats > 0,
                              child: Text('${batch['title']} · ${seats > 0 ? '$seats seats left' : 'Full'}'),
                            );
                          }).toList(),
                          onChanged: submitting ? null : (value) => setState(() { selectedBatchId = value; error = null; }),
                        ),
                      ],
                      const SizedBox(height: 18),
                      Card(child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('Amount due', style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 6),
                          Text('৳${payableAmount.toStringAsFixed(2)}',
                              style: Theme.of(context).textTheme.headlineMedium),
                          if (selectedBatch?['discount_amount'] != null &&
                              (double.tryParse(selectedBatch!['discount_amount'].toString()) ?? 0) > 0)
                            Text('Flash Sale discount: ৳${selectedBatch!['discount_amount']}'),
                          if (batches.isEmpty &&
                              (double.tryParse((checkout?['pricing']?['discount_amount'] ?? '0').toString()) ?? 0) > 0)
                            Text('Flash Sale discount: ৳${checkout?['pricing']?['discount_amount']}'),
                        ]),
                      )),
                      if (payableAmount > 0 && manualMethods.isNotEmpty && pendingPayment == null) ...[
                        const SizedBox(height: 16),
                        DropdownButtonFormField<String>(
                          initialValue: selectedMethod,
                          decoration: const InputDecoration(labelText: 'Manual payment method', border: OutlineInputBorder()),
                          items: manualMethods.map((method) => DropdownMenuItem<String>(
                            value: method['key']?.toString(),
                            child: Text(method['label']?.toString() ?? method['key'].toString()),
                          )).toList(),
                          onChanged: submitting ? null : (value) => setState(() => selectedMethod = value),
                        ),
                        if (selectedMethodData != null) ...[
                          const SizedBox(height: 12),
                          Card(child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(selectedMethodData!['label']?.toString() ?? 'Payment instructions',
                                  style: Theme.of(context).textTheme.titleMedium),
                              if ((selectedMethodData!['account']?.toString() ?? '').isNotEmpty)
                                SelectableText('Account: ${selectedMethodData!['account']}'),
                              if ((selectedMethodData!['instructions']?.toString() ?? '').isNotEmpty)
                                Text(selectedMethodData!['instructions'].toString()),
                            ]),
                          )),
                        ],
                        const SizedBox(height: 12),
                        TextField(
                          controller: senderAccount,
                          keyboardType: TextInputType.phone,
                          decoration: const InputDecoration(labelText: 'Your sender account (optional)', border: OutlineInputBorder()),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: transactionReference,
                          decoration: const InputDecoration(labelText: 'Transaction reference', border: OutlineInputBorder()),
                        ),
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          onPressed: submitting ? null : pickProof,
                          icon: const Icon(Icons.attach_file),
                          label: Text(proofFile?.name ?? 'Attach payment proof (optional)'),
                        ),
                        if (proofFile != null)
                          TextButton(
                            onPressed: submitting ? null : () => setState(() => proofFile = null),
                            child: const Text('Remove attachment'),
                          ),
                      ],
                      if (payableAmount > 0 && manualMethods.isEmpty && !onlineBkash)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 12),
                          child: Text('No payment methods are available. Please contact the academy.'),
                        ),
                      if (error != null) ...[
                        const SizedBox(height: 12),
                        Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                      ],
                      const SizedBox(height: 20),
                      FilledButton.icon(
                        onPressed: submitting ||
                                checkout?['already_enrolled'] == true ||
                                pendingPayment != null ||
                                selectedBatchFull ||
                                (batches.isNotEmpty && selectedBatchId == null) ||
                                (payableAmount > 0 && manualMethods.isEmpty)
                            ? null
                            : submitManual,
                        icon: submitting
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.send),
                        label: Text(payableAmount <= 0 ? 'Enroll free' : 'Submit manual payment'),
                      ),
                      if (payableAmount > 0 && onlineBkash && pendingPayment == null) ...[
                        const SizedBox(height: 10),
                        OutlinedButton.icon(
                          onPressed: submitting ||
                                  checkout?['already_enrolled'] == true ||
                                  pendingPayment != null ||
                                  selectedBatchFull ||
                                  (batches.isNotEmpty && selectedBatchId == null)
                              ? null
                              : payWithBkash,
                          icon: const Icon(Icons.account_balance_wallet_outlined),
                          label: const Text('Pay securely with bKash'),
                        ),
                      ],
                      const SizedBox(height: 12),
                      Text(
                        'Course access is activated only after the academy approves manual payment or bKash verifies the transaction.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
    );
  }
}

class PaymentHistoryPage extends StatefulWidget {
  const PaymentHistoryPage({required this.api, super.key});
  final MobileApi api;
  @override
  State<PaymentHistoryPage> createState() => _PaymentHistoryPageState();
}

class _PaymentHistoryPageState extends State<PaymentHistoryPage> {
  List<dynamic> payments = [];
  bool loading = true;
  String? error;

  @override
  void initState() { super.initState(); load(); }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/my/payments');
      if (!mounted) { return; }
      setState(() => payments = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> showPayment(dynamic item) async {
    final payment = item as Map<String, dynamic>;
    try {
      final response = await widget.api.get('/my/payments/${payment['id']}');
      if (!mounted) { return; }
      final detail = response['data'] as Map<String, dynamic>;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(detail['course']?['title']?.toString() ?? 'Payment details'),
          content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Amount: ৳${detail['amount']} ${detail['currency'] ?? 'BDT'}'),
            Text('Method: ${detail['method']}'),
            Text('Status: ${detail['status']}'),
            Text('Reference: ${detail['transaction_reference']}'),
            if ((detail['sender_account']?.toString() ?? '').isNotEmpty)
              Text('Sender account: ${detail['sender_account']}'),
            if ((detail['admin_note']?.toString() ?? '').isNotEmpty)
              Text('Admin note: ${detail['admin_note']}'),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Close')),
          ],
        ),
      );
    } catch (exception) {
      if (mounted) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString()))); }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Payment history'),
          actions: [IconButton(onPressed: load, icon: const Icon(Icons.refresh), tooltip: 'Refresh')],
        ),
        body: loading
            ? const Center(child: CircularProgressIndicator())
            : error != null
                ? Center(child: Text(error!))
                : RefreshIndicator(
                    onRefresh: load,
                    child: payments.isEmpty
                        ? ListView(children: const [
                            SizedBox(height: 80),
                            Center(child: Text('No course payments found.')),
                          ])
                        : ListView.separated(
                            padding: const EdgeInsets.all(16),
                            itemCount: payments.length,
                            separatorBuilder: (_, __) => const SizedBox(height: 8),
                            itemBuilder: (context, index) {
                              final payment = payments[index] as Map<String, dynamic>;
                              final status = payment['status']?.toString() ?? 'pending';
                              return Card(
                                child: ListTile(
                                  leading: Icon(
                                    status == 'approved' ? Icons.check_circle_outline
                                        : status == 'rejected' ? Icons.error_outline
                                        : Icons.hourglass_top,
                                  ),
                                  title: Text(payment['course']?['title']?.toString() ?? 'Course payment'),
                                  subtitle: Text('৳${payment['amount']} · ${payment['method']} · $status\n${payment['transaction_reference'] ?? ''}'),
                                  isThreeLine: true,
                                  trailing: const Icon(Icons.chevron_right),
                                  onTap: () => showPayment(payment),
                                ),
                              );
                            },
                          ),
                  ),
      );
}

class LessonPlayerPage extends StatefulWidget {
  const LessonPlayerPage({
    required this.api,
    required this.slug,
    required this.lessonId,
    required this.enrolled,
    super.key,
  });
  final MobileApi api;
  final String slug;
  final String lessonId;
  final bool enrolled;

  @override
  State<LessonPlayerPage> createState() => _LessonPlayerPageState();
}

class _LessonPlayerPageState extends State<LessonPlayerPage> {
  Map<String, dynamic>? lesson;
  VideoPlayerController? videoController;
  bool loading = true;
  bool videoLoading = false;
  bool saving = false;
  String? error;
  String? videoError;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    final controller = videoController;
    if (controller != null) {
      unawaited(controller.dispose());
    }
    super.dispose();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get(
        '/courses/${widget.slug}/lessons/${widget.lessonId}',
      );
      if (!mounted) { return; }
      final data = response['lesson'] as Map<String, dynamic>;
      setState(() => lesson = data);
      final streamPath = data['video_stream_path']?.toString();
      if (streamPath != null && streamPath.isNotEmpty) {
        await initializeVideo(streamPath);
      }
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> initializeVideo(String path) async {
    setState(() {
      videoLoading = true;
      videoError = null;
    });
    VideoPlayerController? nextController;
    try {
      final headers = await widget.api.videoHeaders();
      nextController = VideoPlayerController.networkUrl(
        Uri.parse('${MobileApi.baseUrl}$path'),
        httpHeaders: headers,
      );
      await nextController.initialize();
      if (!mounted) {
        await nextController.dispose();
        return;
      }
      final previous = videoController;
      setState(() => videoController = nextController);
      if (previous != null) await previous.dispose();
    } catch (exception) {
      await nextController?.dispose();
      if (mounted) setState(() => videoError = exception.toString());
    } finally {
      if (mounted) setState(() => videoLoading = false);
    }
  }

  Future<void> complete() async {
    setState(() => saving = true);
    try {
      await widget.api.post(
        '/courses/${widget.slug}/lessons/${widget.lessonId}/complete',
      );
      if (mounted) setState(() => lesson?['is_completed'] = true);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  String formatDuration(Duration duration) {
    final hours = duration.inHours;
    final minutes = duration.inMinutes.remainder(60).toString().padLeft(2, '0');
    final seconds = duration.inSeconds.remainder(60).toString().padLeft(2, '0');
    return hours > 0 ? '$hours:$minutes:$seconds' : '$minutes:$seconds';
  }

  Widget buildVideoPlayer() {
    final streamPath = lesson?['video_stream_path']?.toString();
    if (streamPath == null || streamPath.isEmpty) {
      final externalUrl = lesson?['video_url']?.toString();
      if (externalUrl == null || externalUrl.isEmpty) {
        return const SizedBox.shrink();
      }
      return Card(
        child: ListTile(
          leading: const Icon(Icons.link),
          title: const Text('External lesson video'),
          subtitle: SelectableText(externalUrl),
        ),
      );
    }

    if (videoLoading) {
      return const AspectRatio(
        aspectRatio: 16 / 9,
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (videoError != null) {
      return Card(
        child: ListTile(
          leading: const Icon(Icons.error_outline),
          title: const Text('Video could not be loaded'),
          subtitle: Text(videoError!),
          trailing: IconButton(
            tooltip: 'Retry video',
            onPressed: () => initializeVideo(streamPath),
            icon: const Icon(Icons.refresh),
          ),
        ),
      );
    }

    final controller = videoController;
    if (controller == null || !controller.value.isInitialized) {
      return const SizedBox.shrink();
    }

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AspectRatio(
              aspectRatio: controller.value.aspectRatio > 0
                  ? controller.value.aspectRatio
                  : 16 / 9,
              child: VideoPlayer(controller),
            ),
            const SizedBox(height: 8),
            VideoProgressIndicator(
              controller,
              allowScrubbing: true,
              padding: const EdgeInsets.symmetric(vertical: 8),
            ),
            ValueListenableBuilder<VideoPlayerValue>(
              valueListenable: controller,
              builder: (context, value, _) => Row(
                children: [
                  IconButton(
                    tooltip: value.isPlaying ? 'Pause video' : 'Play video',
                    onPressed: () {
                      if (value.isPlaying) {
                        controller.pause();
                      } else {
                        controller.play();
                      }
                    },
                    icon: Icon(
                      value.isPlaying ? Icons.pause_circle : Icons.play_circle,
                      size: 34,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    '${formatDuration(value.position)} / ${formatDuration(value.duration)}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(lesson?['title']?.toString() ?? 'Lesson')),
        body: loading
            ? const Center(child: CircularProgressIndicator())
            : error != null
                ? Center(child: Text(error!))
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(
                        lesson?['title']?.toString() ?? 'Lesson',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 16),
                      buildVideoPlayer(),
                      const SizedBox(height: 16),
                      Text(lesson?['content']?.toString() ?? ''),
                      if (widget.enrolled && lesson?['is_completed'] != true)
                        Padding(
                          padding: const EdgeInsets.only(top: 24),
                          child: FilledButton(
                            onPressed: saving ? null : complete,
                            child: saving
                                ? const CircularProgressIndicator()
                                : const Text('Mark lesson complete'),
                          ),
                        ),
                      if (lesson?['is_completed'] == true)
                        const Chip(label: Text('Completed')),
                    ],
                  ),
      );
}


class AssignmentsPage extends StatefulWidget {
  const AssignmentsPage({required this.api, super.key});
  final MobileApi api;

  @override
  State<AssignmentsPage> createState() => _AssignmentsPageState();
}

class _AssignmentsPageState extends State<AssignmentsPage> {
  List<dynamic> assignments = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/my/assignments');
      if (!mounted) { return; }
      setState(() => assignments = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('My Assignments')),
    body: loading
      ? const Center(child: CircularProgressIndicator())
      : error != null
        ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(error!), TextButton(onPressed: load, child: const Text('Retry')),
          ]))
        : assignments.isEmpty
          ? const Center(child: Text('No assignments available.'))
          : RefreshIndicator(
              onRefresh: load,
              child: ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: assignments.length,
                itemBuilder: (context, index) {
                  final item = assignments[index] as Map<String, dynamic>;
                  final submission = item['submission'] as Map<String, dynamic>?;
                  return Card(child: ListTile(
                    title: Text(item['title']?.toString() ?? 'Assignment'),
                    subtitle: Text([
                      item['course']?.toString() ?? '',
                      'Due: ${item['due_at'] ?? '—'}',
                      'Status: ${submission?['status'] ?? 'Not submitted'}',
                    ].where((value) => value.isNotEmpty).join(' • ')),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => AssignmentDetailPage(api: widget.api, id: item['id'] as int),
                    )).then((_) => load()),
                  ));
                },
              ),
            ),
  );
}

class AssignmentDetailPage extends StatefulWidget {
  const AssignmentDetailPage({required this.api, required this.id, super.key});
  final MobileApi api;
  final int id;

  @override
  State<AssignmentDetailPage> createState() => _AssignmentDetailPageState();
}

class _AssignmentDetailPageState extends State<AssignmentDetailPage> {
  final answer = TextEditingController();
  Map<String, dynamic>? assignment;
  bool loading = true;
  bool saving = false;
  PlatformFile? attachment;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    answer.dispose();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/assignments/${widget.id}');
      if (!mounted) { return; }
      final item = response['assignment'] as Map<String, dynamic>;
      setState(() {
        assignment = item;
        answer.text = (item['submission'] as Map<String, dynamic>?)?['answer']?.toString() ?? '';
      });
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> pickAttachment() async {
    try {
      final picked = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
      );
      if (picked == null) { return; }
      final bytes = await picked.readAsBytes();
      if (bytes.lengthInBytes > 10 * 1024 * 1024) {
        if (mounted) { setState(() => error = 'Attachment must be 10 MB or smaller.'); }
        return;
      }
      if (mounted) { setState(() { attachment = picked; error = null; }); }
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    }
  }

  Future<void> submit() async {
    setState(() { saving = true; error = null; });
    try {
      await widget.api.postMultipart(
        '/assignments/${widget.id}/submit',
        {'answer': answer.text.trim()},
        attachment: attachment,
      );
      if (!mounted) { return; }
      setState(() => attachment = null);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Assignment submitted.')));
      await load();
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = assignment;
    final submission = item?['submission'] as Map<String, dynamic>?;
    final canSubmit = item != null && item['is_closed'] != true && submission?['status'] != 'submitted';
    return Scaffold(
      appBar: AppBar(title: Text(item?['title']?.toString() ?? 'Assignment')),
      body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null && item == null
          ? Center(child: Text(error!))
          : ListView(padding: const EdgeInsets.all(16), children: [
              Text(item?['course']?.toString() ?? '', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Text('Due: ${item?['due_at'] ?? '—'}'),
              Text('Maximum marks: ${item?['max_marks'] ?? 0}'),
              const Divider(height: 24),
              Text(item?['instructions']?.toString() ?? ''),
              const SizedBox(height: 20),
              if (submission != null) ...[
                Text('Submission: ${submission['status']}'),
                if (submission['score'] != null) Text('Score: ${submission['score']}'),
                if ((submission['feedback'] ?? '').toString().isNotEmpty) Text('Feedback: ${submission['feedback']}'),
                const SizedBox(height: 12),
              ],
              if (canSubmit) ...[
                TextField(
                  controller: answer,
                  minLines: 6,
                  maxLines: 12,
                  maxLength: 50000,
                  onChanged: (_) => setState(() {}),
                  decoration: const InputDecoration(
                    labelText: 'Your answer',
                    alignLabelWithHint: true,
                    border: OutlineInputBorder(),
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: saving ? null : pickAttachment,
                  icon: const Icon(Icons.attach_file),
                  label: Text(attachment?.name ?? 'Attach file (PDF, DOC, DOCX, JPG, PNG • max 10 MB)'),
                ),
                if (attachment != null)
                  TextButton.icon(
                    onPressed: saving ? null : () => setState(() => attachment = null),
                    icon: const Icon(Icons.close),
                    label: const Text('Remove attachment'),
                  ),
                if (submission?['attachment_name'] != null && attachment == null)
                  Text('Previously submitted file: ${submission?['attachment_name']}'),
                FilledButton(
                  onPressed: saving || (answer.text.trim().isEmpty && attachment == null && submission?['has_attachment'] != true)
                      ? null
                      : submit,
                  child: saving ? const CircularProgressIndicator() : const Text('Submit assignment'),
                ),
              ] else if (item?['is_closed'] == true)
                const Chip(label: Text('Submission closed')),
              if (error != null) Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ]),
    );
  }
}

class ExamsPage extends StatefulWidget {
  const ExamsPage({required this.api, super.key});
  final MobileApi api;

  @override
  State<ExamsPage> createState() => _ExamsPageState();
}

class _ExamsPageState extends State<ExamsPage> {
  List<dynamic> exams = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/my/exams');
      if (!mounted) { return; }
      setState(() => exams = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('My Exams'),
      actions: [
        IconButton(onPressed: load, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
      ],
    ),
    body: loading
      ? const Center(child: CircularProgressIndicator())
      : error != null
        ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(error!), TextButton(onPressed: load, child: const Text('Retry')),
          ]))
        : exams.isEmpty
          ? const Center(child: Text('No exams are available for your active batches.'))
          : RefreshIndicator(
              onRefresh: load,
              child: ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: exams.length,
                itemBuilder: (context, index) {
                  final item = exams[index] as Map<String, dynamic>;
                  final attempt = item['attempt'] as Map<String, dynamic>?;
                  final status = attempt?['status']?.toString();
                  final statusLabel = status == 'submitted'
                      ? 'Submitted • Score: ${attempt?['score'] ?? '—'}'
                      : status == 'in_progress'
                          ? 'In progress'
                          : 'Not started';
                  return Card(
                    child: ListTile(
                      leading: const CircleAvatar(child: Icon(Icons.quiz_outlined)),
                      title: Text(item['title']?.toString() ?? 'Exam'),
                      subtitle: Text([
                        item['course']?.toString() ?? '',
                        'Starts: ${item['starts_at'] ?? '—'}',
                        'Ends: ${item['ends_at'] ?? '—'}',
                        statusLabel,
                      ].where((value) => value.isNotEmpty).join(' • ')),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => ExamDetailPage(
                          api: widget.api,
                          examId: (item['id'] as num).toInt(),
                        ),
                      )).then((_) => load()),
                    ),
                  );
                },
              ),
            ),
  );
}

class ExamDetailPage extends StatefulWidget {
  const ExamDetailPage({required this.api, required this.examId, super.key});
  final MobileApi api;
  final int examId;

  @override
  State<ExamDetailPage> createState() => _ExamDetailPageState();
}

class _ExamDetailPageState extends State<ExamDetailPage> {
  Map<String, dynamic>? exam;
  Map<String, dynamic>? attempt;
  Map<String, dynamic>? result;
  List<dynamic> questions = [];
  final Map<int, int> answers = {};
  Timer? clock;
  DateTime? deadline;
  int secondsRemaining = 0;
  bool loading = true;
  bool starting = false;
  bool saving = false;
  bool submitting = false;
  String? error;
  int? attemptId;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    clock?.cancel();
    super.dispose();
  }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/exams/${widget.examId}');
      final data = response['data'] as Map<String, dynamic>;
      final nextAttempt = data['attempt'] as Map<String, dynamic>?;
      final nextQuestions = data['questions'] as List<dynamic>? ?? [];
      final nextResult = data['result'] as Map<String, dynamic>?;
      if (!mounted) { return; }
      setState(() {
        exam = data;
        attempt = nextAttempt;
        attemptId = (nextAttempt?['id'] as num?)?.toInt();
        questions = nextQuestions;
        result = nextResult;
        answers.clear();
        for (final raw in nextQuestions) {
          final question = raw as Map<String, dynamic>;
          final selected = question['selected_option'];
          if (selected is num) {
            answers[(question['id'] as num).toInt()] = selected.toInt();
          }
        }
      });
      final deadlineValue = data['deadline']?.toString();
      if (deadlineValue != null && deadlineValue.isNotEmpty &&
          nextAttempt?['status'] == 'in_progress') {
        _setDeadline(DateTime.parse(deadlineValue).toLocal());
      } else {
        clock?.cancel();
        deadline = null;
      }
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  void _setDeadline(DateTime value) {
    clock?.cancel();
    deadline = value;
    _tick();
    clock = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  void _tick() {
    final end = deadline;
    if (end == null || !mounted) return;
    final remaining = end.difference(DateTime.now()).inSeconds;
    setState(() => secondsRemaining = remaining > 0 ? remaining : 0);
    if (remaining <= 0) {
      clock?.cancel();
      if (!submitting && result == null) submit(automatic: true);
    }
  }

  String _formatTime(int value) {
    final minutes = value ~/ 60;
    final seconds = value % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  Map<String, dynamic> _answerPayload() => {
    for (final entry in answers.entries) entry.key.toString(): entry.value,
  };

  Future<void> startOrResume() async {
    setState(() { starting = true; error = null; });
    try {
      final response = await widget.api.post('/exams/${widget.examId}/start');
      final data = response['data'] as Map<String, dynamic>;
      final nextAttempt = data['attempt'] as Map<String, dynamic>;
      final nextQuestions = data['questions'] as List<dynamic>? ?? [];
      if (!mounted) { return; }
      setState(() {
        attempt = nextAttempt;
        attemptId = (nextAttempt['id'] as num).toInt();
        questions = nextQuestions;
        result = nextAttempt['status'] == 'submitted'
            ? {'score': nextAttempt['score']}
            : null;
        answers.clear();
        for (final raw in nextQuestions) {
          final question = raw as Map<String, dynamic>;
          final selected = question['selected_option'];
          if (selected is num) {
            answers[(question['id'] as num).toInt()] = selected.toInt();
          }
        }
      });
      final deadlineValue = data['deadline']?.toString();
      if (deadlineValue != null && deadlineValue.isNotEmpty &&
          nextAttempt['status'] == 'in_progress') {
        _setDeadline(DateTime.parse(deadlineValue).toLocal());
      }
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) {
        setState(() => starting = false);
      }
    }
  }

  Future<void> saveAnswers() async {
    final id = attemptId;
    if (id == null || answers.isEmpty || saving || submitting) return;
    setState(() { saving = true; error = null; });
    try {
      await widget.api.putJson('/exam-attempts/$id/answers', {
        'answers': _answerPayload(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Answers saved securely.')),
        );
      }
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) {
        setState(() => saving = false);
      }
    }
  }

  Future<void> submit({bool automatic = false}) async {
    final id = attemptId;
    if (id == null || submitting || result != null) return;
    setState(() { submitting = true; error = null; });
    try {
      final response = await widget.api.postJson('/exam-attempts/$id/submit', {
        'answers': _answerPayload(),
      });
      if (!mounted) { return; }
      clock?.cancel();
      setState(() {
        result = response['data'] as Map<String, dynamic>;
        attempt = {...?attempt, 'status': 'submitted', 'score': result?['score']};
        questions = [];
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(automatic ? 'Time ended. Your exam was submitted.' : 'Exam submitted successfully.')),
      );
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => submitting = false); }
    }
  }

  Widget _questionCard(Map<String, dynamic> question) {
    final id = (question['id'] as num).toInt();
    final options = question['options'] as List<dynamic>? ?? [];
    final selected = answers[id];
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${question['position'] ?? ''}. ${question['question'] ?? ''}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            RadioGroup<int>(
              groupValue: selected,
              onChanged: (value) {
                if (value != null && !saving && !submitting) {
                  setState(() => answers[id] = value);
                }
              },
              child: Column(
                children: List.generate(options.length, (index) => RadioListTile<int>(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  value: index,
                  enabled: !saving && !submitting,
                  title: Text(options[index].toString()),
                )),
              ),
            ),
            Text(
              '${question['marks'] ?? 0} marks',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final item = exam;
    final inProgress = attempt?['status'] == 'in_progress' && result == null;
    final submitted = result != null || attempt?['status'] == 'submitted';
    return Scaffold(
      appBar: AppBar(
        title: Text(item?['title']?.toString() ?? 'Exam'),
        actions: [
          IconButton(onPressed: load, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
        ],
      ),
      body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null && item == null
          ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text(error!), TextButton(onPressed: load, child: const Text('Retry')),
            ]))
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(item?['instructions']?.toString() ?? ''),
                const SizedBox(height: 8),
                Text('Starts: ${item?['starts_at'] ?? '—'}'),
                Text('Ends: ${item?['ends_at'] ?? '—'}'),
                Text('Duration: ${item?['duration_minutes'] ?? '—'} minutes'),
                const Divider(height: 24),
                if (submitted) ...[
                  Card(
                    child: ListTile(
                      leading: Icon(
                        result?['passed'] == true ? Icons.emoji_events : Icons.assignment_turned_in,
                      ),
                      title: Text('Exam submitted • Score: ${result?['score'] ?? attempt?['score'] ?? '—'}'),
                      subtitle: Text(
                        result?['passed'] == true
                            ? 'Passed'
                            : 'Pass marks: ${result?['pass_marks'] ?? item?['pass_marks'] ?? '—'}',
                      ),
                    ),
                  ),
                ] else if (!inProgress) ...[
                  const Text('Start the exam when it is available. Your attempt and question order will be saved securely.'),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: starting ? null : startOrResume,
                    icon: const Icon(Icons.play_arrow),
                    label: starting
                        ? const CircularProgressIndicator()
                        : const Text('Start exam'),
                  ),
                ] else ...[
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.timer_outlined),
                      title: Text('Time remaining: ${_formatTime(secondsRemaining)}'),
                      subtitle: const Text('The server controls the official exam deadline.'),
                    ),
                  ),
                  ...questions.map((raw) => _questionCard(raw as Map<String, dynamic>)),
                  if (error != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                    ),
                  OutlinedButton.icon(
                    onPressed: saving || submitting || answers.isEmpty ? null : saveAnswers,
                    icon: const Icon(Icons.save_outlined),
                    label: saving ? const CircularProgressIndicator() : const Text('Save answers'),
                  ),
                  const SizedBox(height: 8),
                  FilledButton.icon(
                    onPressed: saving || submitting ? null : () => submit(),
                    icon: const Icon(Icons.send),
                    label: submitting
                        ? const CircularProgressIndicator()
                        : const Text('Submit exam'),
                  ),
                ],
                if (error != null && item != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  ),
              ],
            ),
    );
  }
}


class StudentNoticesPage extends StatefulWidget {
  const StudentNoticesPage({required this.api, super.key});
  final MobileApi api;

  @override
  State<StudentNoticesPage> createState() => _StudentNoticesPageState();
}

class _StudentNoticesPageState extends State<StudentNoticesPage> {
  List<dynamic> notices = [];
  bool loading = true;
  String? error;
  int unreadCount = 0;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/my/notices');
      if (!mounted) return;
      setState(() {
        notices = response['data'] as List<dynamic>? ?? [];
        final meta = response['meta'] as Map<String, dynamic>? ?? {};
        unreadCount = (meta['unread_count'] as num?)?.toInt() ?? 0;
      });
    } catch (exception) {
      if (mounted) {
        setState(() => error = exception.toString());
      }
    } finally {
      if (mounted) {
        setState(() => loading = false);
      }
    }
  }

  Future<void> markRead(Map<String, dynamic> notice) async {
    try {
      await widget.api.postJson('/my/notices/${notice['id']}/read', const {});
      if (mounted) {
        setState(() {
          notice['is_read'] = true;
          unreadCount = (unreadCount - 1).clamp(0, 999999).toInt();
        });
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(unreadCount > 0 ? 'Academy notices ($unreadCount)' : 'Academy notices'),
      actions: [IconButton(onPressed: load, icon: const Icon(Icons.refresh))],
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
            ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text(error!),
                TextButton(onPressed: load, child: const Text('Retry')),
              ]))
            : notices.isEmpty
                ? const Center(child: Text('No notices are available right now.'))
                : RefreshIndicator(
                    onRefresh: load,
                    child: ListView.builder(
                      padding: const EdgeInsets.all(12),
                      itemCount: notices.length,
                      itemBuilder: (context, index) {
                        final notice = notices[index] as Map<String, dynamic>;
                        final read = notice['is_read'] == true;
                        return Card(
                          child: ListTile(
                            leading: Icon(read ? Icons.notifications_none : Icons.notifications_active,
                                color: read ? null : Theme.of(context).colorScheme.primary),
                            title: Text(notice['title']?.toString() ?? 'Academy notice',
                                style: TextStyle(fontWeight: read ? FontWeight.normal : FontWeight.bold)),
                            subtitle: Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Text(notice['body']?.toString() ?? ''),
                                if (notice['course'] is Map<String, dynamic>)
                                  Text('Course: ${(notice['course'] as Map<String, dynamic>)['title'] ?? ''}'),
                                if (notice['published_at'] != null)
                                  Text(notice['published_at'].toString(), style: Theme.of(context).textTheme.bodySmall),
                              ]),
                            ),
                            isThreeLine: true,
                            trailing: read ? const Icon(Icons.done, size: 18) : const Icon(Icons.mark_email_read_outlined),
                            onTap: read ? null : () => markRead(notice),
                          ),
                        );
                      },
                    ),
                  ),
  );
}

class CourseReviewsPanel extends StatefulWidget {
  const CourseReviewsPanel({required this.api, required this.slug, required this.canReview, super.key});
  final MobileApi api;
  final String slug;
  final bool canReview;

  @override
  State<CourseReviewsPanel> createState() => _CourseReviewsPanelState();
}

class _CourseReviewsPanelState extends State<CourseReviewsPanel> {
  final controller = TextEditingController();
  List<dynamic> reviews = [];
  bool loading = true;
  bool submitting = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get('/courses/${widget.slug}/reviews');
      if (mounted) {
        setState(() {
          reviews = response['data'] as List<dynamic>? ?? [];
          loading = false;
        });
      }
    } catch (exception) {
      if (mounted) {
        setState(() {
          error = exception.toString();
          loading = false;
        });
      }
    }
  }

  Future<void> submit() async {
    final body = controller.text.trim();
    if (body.length < 2 || submitting) return;
    setState(() => submitting = true);
    try {
      await widget.api.postJson('/my/courses/${widget.slug}/reviews', {'body': body});
      controller.clear();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Review submitted for admin moderation.')),
        );
      }
    } catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      if (widget.canReview) ...[
        TextField(
          controller: controller,
          minLines: 2,
          maxLines: 5,
          maxLength: 2000,
          decoration: const InputDecoration(
            hintText: 'Share your experience with this course',
            border: OutlineInputBorder(),
          ),
        ),
        Align(
          alignment: Alignment.centerRight,
          child: FilledButton(
            onPressed: submitting ? null : submit,
            child: Text(submitting ? 'Submitting…' : 'Submit review'),
          ),
        ),
      ],
      if (loading) const Center(child: CircularProgressIndicator()),
      if (error != null) Text(error!),
      if (!loading && reviews.isEmpty) const Text('No approved reviews yet.'),
      ...reviews.map((item) {
        final review = item as Map<String, dynamic>;
        return Card(
          child: ListTile(
            leading: const CircleAvatar(child: Icon(Icons.person_outline)),
            title: Text(review['author']?.toString() ?? 'Student'),
            subtitle: Text(review['body']?.toString() ?? ''),
          ),
        );
      }),
    ],
  );
}
