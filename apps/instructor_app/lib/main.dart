import 'package:material_ui/material_ui.dart';
import 'api_client.dart';

void main() => runApp(const InstructorApp());

class InstructorApp extends StatelessWidget {
  const InstructorApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: "Alom's English Center — Instructor",
    debugShowCheckedModeBanner: false,
    theme: ThemeData(colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1769AA)), useMaterial3: true),
    home: const InstructorGate(),
  );
}

class InstructorGate extends StatefulWidget {
  const InstructorGate({super.key});
  @override
  State<InstructorGate> createState() => _InstructorGateState();
}
class _InstructorGateState extends State<InstructorGate> {
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
      ? InstructorHome(api: api, onLogout: () => setState(() => signedIn = false))
      : InstructorLogin(api: api, onLogin: () => setState(() => signedIn = true));
  }
}

class InstructorLogin extends StatefulWidget {
  const InstructorLogin({required this.api, required this.onLogin, super.key});
  final MobileApi api;
  final VoidCallback onLogin;
  @override
  State<InstructorLogin> createState() => _InstructorLoginState();
}
class _InstructorLoginState extends State<InstructorLogin> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool busy = false;
  String? error;
  @override
  void dispose() { email.dispose(); password.dispose(); super.dispose(); }
  Future<void> submit() async {
    setState(() { busy = true; error = null; });
    try {
      await widget.api.login(email.text.trim(), password.text, 'Aloms Instructor App');
      widget.onLogin();
    } catch (e) { setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => busy = false); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    body: Center(child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 440),
      child: Padding(padding: const EdgeInsets.all(24), child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.co_present, size: 64),
          Text("Alom's English Center", textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
          const Text('Instructor App', textAlign: TextAlign.center),
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

class InstructorHome extends StatefulWidget {
  const InstructorHome({required this.api, required this.onLogout, super.key});
  final MobileApi api;
  final VoidCallback onLogout;
  @override
  State<InstructorHome> createState() => _InstructorHomeState();
}
class _InstructorHomeState extends State<InstructorHome> {
  List<dynamic> courses = [];
  bool loading = true;
  String? error;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/instructor/courses');
      setState(() => courses = response['data'] as List<dynamic>? ?? []);
    } catch (e) { setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  Future<void> showStudents(Map<String, dynamic> course) async {
    try {
      final response = await widget.api.get("/instructor/courses/${course['slug']}/students");
      if (!mounted) {
        return;
      }
      final students = response['data'] as List<dynamic>? ?? [];
      showModalBottomSheet<void>(context: context, builder: (context) => SafeArea(child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Students — ${course['title']}', style: Theme.of(context).textTheme.titleLarge),
          ...students.map((item) {
            final student = item['student'] as Map<String, dynamic>;
            return ListTile(title: Text(student['name']?.toString() ?? 'Student'), subtitle: Text(student['email']?.toString() ?? ''));
          }),
          if (students.isEmpty) const ListTile(title: Text('No enrolled students yet.')),
        ],
      )));
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }
  Future<void> logout() async { await widget.api.logout(); widget.onLogout(); }
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Instructor Workspace'), actions: [IconButton(onPressed: logout, icon: const Icon(Icons.logout), tooltip: 'Sign out')]),
    body: RefreshIndicator(
      onRefresh: load,
      child: loading ? const Center(child: CircularProgressIndicator())
        : error != null ? ListView(children: [const SizedBox(height: 80), Center(child: Text(error!)), TextButton(onPressed: load, child: const Text('Retry'))])
        : ListView(padding: const EdgeInsets.all(16), children: [
          Text('My Assigned Courses', style: Theme.of(context).textTheme.titleLarge),
          if (courses.isEmpty) const ListTile(title: Text('No courses assigned.')),
          ...courses.map((item) {
            final course = item as Map<String, dynamic>;
            return Card(child: ListTile(
              leading: const Icon(Icons.menu_book),
              title: Text(course['title']?.toString() ?? 'Course'),
              subtitle: Text("${course['students_count'] ?? 0} enrolled students · ${course['status'] ?? ''}"),
              trailing: const Icon(Icons.people_outline),
              onTap: () => showStudents(course),
            ));
          }),
        ]),
    ),
  );
}
