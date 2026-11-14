import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:material_ui/material_ui.dart';
import 'api_client.dart';
import 'package:video_player/video_player.dart';

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
      if (!mounted) return;
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
          onPressed: openWishlist,
          icon: const Icon(Icons.favorite_outline),
          tooltip: 'My wishlist',
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
      if (!mounted) return;
      setState(() => courses = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
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
                        const SizedBox(height: 20),
                        Text('Course curriculum', style: Theme.of(context).textTheme.titleLarge),
                        if (lessons.isEmpty)
                          const ListTile(title: Text('No preview lessons are available.')),
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
      if (!mounted) return;
      final data = response['lesson'] as Map<String, dynamic>;
      setState(() => lesson = data);
      final streamPath = data['video_stream_path']?.toString();
      if (streamPath != null && streamPath.isNotEmpty) {
        await initializeVideo(streamPath);
      }
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
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
      if (mounted) setState(() => error = exception.toString());
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
      if (!mounted) return;
      setState(() => assignments = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
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
      if (!mounted) return;
      final item = response['assignment'] as Map<String, dynamic>;
      setState(() {
        assignment = item;
        answer.text = (item['submission'] as Map<String, dynamic>?)?['answer']?.toString() ?? '';
      });
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> pickAttachment() async {
    try {
      final result = await FilePicker.pickFiles(
        allowMultiple: false,
        type: FileType.custom,
        allowedExtensions: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
        withData: true,
      );
      if (result == null || result.files.isEmpty) return;
      final picked = result.files.first;
      final bytes = picked.bytes;
      if (bytes == null) {
        if (mounted) setState(() => error = 'The selected file could not be read.');
        return;
      }
      if (bytes.lengthInBytes > 10 * 1024 * 1024) {
        if (mounted) setState(() => error = 'Attachment must be 10 MB or smaller.');
        return;
      }
      if (mounted) setState(() { attachment = picked; error = null; });
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
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
      if (!mounted) return;
      setState(() => attachment = null);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Assignment submitted.')));
      await load();
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
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
      if (!mounted) return;
      setState(() => exams = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
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
      if (!mounted) return;
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
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
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
      if (!mounted) return;
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
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => starting = false);
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
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => saving = false);
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
      if (!mounted) return;
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
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => submitting = false);
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
