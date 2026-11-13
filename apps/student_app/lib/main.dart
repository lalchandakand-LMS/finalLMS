import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'api_client.dart';

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
      if (!mounted) return;
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
      if (mounted) setState(() => error = exception.toString());
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
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  void _handleOutcome(LoginOutcome outcome) {
    if (!mounted) return;
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
    if (busy) return;
    if (!silent && mounted) setState(() { busy = true; error = null; });
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
      if (mounted && !silent) setState(() => error = exception.toString());
    } finally {
      if (!silent && mounted) setState(() => busy = false);
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
  List<dynamic> catalog = [];
  List<dynamic> myCourses = [];
  bool loading = true;
  String? error;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final results = await Future.wait([widget.api.get('/courses'), widget.api.get('/my/courses')]);
      setState(() {
        catalog = results[0]['data'] as List<dynamic>? ?? [];
        myCourses = results[1]['data'] as List<dynamic>? ?? [];
      });
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        widget.onLogout();
      } else {
        setState(() => error = e.toString());
      }
    }
    finally { if (mounted) { setState(() => loading = false); } }
  }
  Future<void> logout() async { await widget.api.logout(); widget.onLogout(); }
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Student Learning'), actions: [IconButton(onPressed: logout, icon: const Icon(Icons.logout), tooltip: 'Sign out')]),
    body: RefreshIndicator(
      onRefresh: load,
      child: loading ? const Center(child: CircularProgressIndicator())
        : error != null ? ListView(children: [const SizedBox(height: 80), Center(child: Text(error!)), TextButton(onPressed: load, child: const Text('Retry'))])
        : ListView(padding: const EdgeInsets.all(16), children: [
          Text('My Courses', style: Theme.of(context).textTheme.titleLarge),
          if (myCourses.isEmpty) const ListTile(title: Text('No active courses yet.')),
          ...myCourses.map((item) {
            final course = item['course'] as Map<String, dynamic>;
            final progress = item['progress_percent'] as num? ?? 0;
            return Card(child: ListTile(
              title: Text(course['title']?.toString() ?? 'Course'),
              subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('$progress% completed'),
                const SizedBox(height: 6),
                LinearProgressIndicator(value: progress.toDouble() / 100),
              ]),
            ));
          }),
          const SizedBox(height: 20),
          Text('Explore Courses', style: Theme.of(context).textTheme.titleLarge),
          ...catalog.map((item) => Card(child: ListTile(
            leading: const Icon(Icons.menu_book),
            title: Text(item['title']?.toString() ?? 'Course'),
            subtitle: Text("৳${item['price'] ?? 0}"),
            onTap: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => CourseDetailsPage(
                api: widget.api,
                slug: item['slug'].toString(),
              ),
            )),
          ))),
        ]),
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
      });
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Course details')),
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
  bool loading = true;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.get(
        '/courses/${widget.slug}/lessons/${widget.lessonId}',
      );
      if (mounted) { setState(() => lesson = response['lesson'] as Map<String, dynamic>); }
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> complete() async {
    setState(() => saving = true);
    try {
      await widget.api.post(
        '/courses/${widget.slug}/lessons/${widget.lessonId}/complete',
      );
      if (mounted) { setState(() => lesson?['is_completed'] = true); }
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => saving = false); }
    }
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
                      if ((lesson?['video_url'] ?? '').toString().isNotEmpty)
                        SelectableText('Video: ${lesson?['video_url']}'),
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
