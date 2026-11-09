import 'package:flutter/material.dart';
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
      if (!mounted) {\n        return;\n      }
      setState(() { signedIn = token != null; loading = false; });
    });
  }
  @override
  Widget build(BuildContext context) {
    if (loading) return const Scaffold(body: Center(child: CircularProgressIndicator()));
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
    finally { if (mounted) setState(() => loading = false); }
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
          ))),
        ]),
    ),
  );
}
