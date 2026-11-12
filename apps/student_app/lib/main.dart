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
    api.readToken().then((token) {
      if (!mounted) {
        return;
      }
      setState(() { signedIn = token != null; loading = false; });
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
class _StudentLoginState extends State<StudentLogin> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool busy = false;
  String? error;
  @override
  void dispose() { email.dispose(); password.dispose(); super.dispose(); }
  Future<void> submit() async {
    setState(() { busy = true; error = null; });
    try {
      await widget.api.login(email.text.trim(), password.text, 'Aloms Student App');
      widget.onLogin();
    } catch (e) { setState(() => error = e.toString()); }
    finally { if (mounted) { setState(() => busy = false); } }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    body: Center(child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 440),
      child: Padding(padding: const EdgeInsets.all(24), child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.school, size: 64),
          Text("Alom's English Center", textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
          const Text('Student App', textAlign: TextAlign.center),
          const SizedBox(height: 24),
          TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder())),
          const SizedBox(height: 12),
          TextField(controller: password, obscureText: true, decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder())),
          if (error != null) Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          const SizedBox(height: 16),
          FilledButton(onPressed: busy ? null : submit, child: busy ? const CircularProgressIndicator() : const Text('Sign in')),
        ],
      )),
    )),
  );
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
    } catch (e) { setState(() => error = e.toString()); }
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
