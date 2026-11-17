import 'package:material_ui/material_ui.dart';
import 'api_client.dart';
import 'attendance_page.dart';

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
    api.restoreSession().then((restored) {
      if (!mounted) {
        return;
      }
      setState(() { signedIn = restored; loading = false; });
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
  final code = TextEditingController();
  bool busy = false;
  bool challengePending = false;
  String? error;
  String? statusMessage;
  @override
  void initState() {
    super.initState();
    widget.api.hasPendingChallenge().then((pending) {
      if (mounted && pending) { setState(() => challengePending = true); }
    });
  }
  @override
  void dispose() { email.dispose(); password.dispose(); code.dispose(); super.dispose(); }
  Future<void> submit() async {
    setState(() { busy = true; error = null; });
    try {
      final outcome = await widget.api.login(email.text.trim(), password.text, 'Aloms Instructor App');
      if (!mounted) { return; }
      if (outcome.state == LoginState.authenticated) {
        widget.onLogin();
      } else {
        setState(() {
          challengePending = true;
          statusMessage = outcome.message;
        });
      }
    } catch (e) {
      if (mounted) { setState(() => error = e.toString()); }
    } finally {
      if (mounted) { setState(() => busy = false); }
    }
  }

  Future<void> verifyCode() async {
    setState(() { busy = true; error = null; statusMessage = null; });
    try {
      final outcome = await widget.api.verifyDevice(code.text.trim());
      if (!mounted) return;
      if (outcome.state == LoginState.authenticated) {
        widget.onLogin();
      } else {
        setState(() => statusMessage = outcome.message);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> checkApproval() async {
    setState(() { busy = true; error = null; statusMessage = null; });
    try {
      final outcome = await widget.api.checkDeviceStatus();
      if (!mounted) return;
      if (outcome.state == LoginState.authenticated) {
        widget.onLogin();
      } else {
        setState(() => statusMessage = outcome.message);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> startOver() async {
    await widget.api.clearLoginChallenge();
    if (mounted) {
      setState(() {
        challengePending = false;
        statusMessage = null;
        error = null;
        code.clear();
      });
    }
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
          if (!challengePending) ...[
            TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder())),
            const SizedBox(height: 12),
            TextField(controller: password, obscureText: true, decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder())),
          ] else ...[
            const Text('Device verification', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            const Text('Enter the 6-digit code sent to the mobile number registered with the academy. New devices may also require administrator approval.', textAlign: TextAlign.center),
            const SizedBox(height: 12),
            TextField(controller: code, keyboardType: TextInputType.number, maxLength: 6, onChanged: (_) => setState(() {}), decoration: const InputDecoration(labelText: 'SMS verification code', border: OutlineInputBorder())),
          ],
          if (statusMessage != null) Text(statusMessage!, style: TextStyle(color: Theme.of(context).colorScheme.primary)),
          if (error != null) Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          const SizedBox(height: 16),
          if (!challengePending)
            FilledButton(onPressed: busy ? null : submit, child: busy ? const CircularProgressIndicator() : const Text('Sign in'))
          else ...[
            FilledButton(onPressed: busy || code.text.trim().length != 6 ? null : verifyCode, child: busy ? const CircularProgressIndicator() : const Text('Verify code')),
            const SizedBox(height: 8),
            OutlinedButton(onPressed: busy ? null : checkApproval, child: const Text('Check device approval')),
            TextButton(onPressed: busy ? null : startOver, child: const Text('Use another login')),
          ],
        ],
      )),
    )),
  );
}

class InstructorAssessmentsPage extends StatefulWidget {
  const InstructorAssessmentsPage({required this.api, super.key});
  final MobileApi api;

  @override
  State<InstructorAssessmentsPage> createState() => _InstructorAssessmentsPageState();
}

class _InstructorAssessmentsPageState extends State<InstructorAssessmentsPage> {
  List<dynamic> exams = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) { setState(() { loading = true; error = null; }); }
    try {
      final response = await widget.api.get('/instructor/exams');
      if (!mounted) { return; }
      setState(() => exams = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  Future<void> openResults(Map<String, dynamic> exam) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => InstructorAssessmentResultsPage(api: widget.api, examId: (exam['id'] as num).toInt()),
    ));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Assessments & Results'),
      actions: [IconButton(onPressed: loading ? null : load, icon: const Icon(Icons.refresh))],
    ),
    body: loading
      ? const Center(child: CircularProgressIndicator())
      : error != null
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
                const Text('Review published assessments for your assigned batches and inspect student scores and pass rates.'),
                const SizedBox(height: 12),
                if (exams.isEmpty)
                  const Padding(padding: EdgeInsets.all(28), child: Center(child: Text('No assessments found for your batches.'))),
                ...exams.map((item) {
                  final exam = item as Map<String, dynamic>;
                  return Card(child: ListTile(
                    leading: const Icon(Icons.quiz_outlined),
                    title: Text(exam['title']?.toString() ?? 'Assessment'),
                    subtitle: Text(
                      '${exam['course'] ?? ''} · ${exam['batch'] ?? ''}\n'
                      '${exam['questions_count'] ?? 0} questions · ${exam['submitted_attempts_count'] ?? 0} submitted',
                    ),
                    isThreeLine: true,
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => openResults(exam),
                  ));
                }),
              ],
            ),
          ),
  );
}

class InstructorAssessmentResultsPage extends StatefulWidget {
  const InstructorAssessmentResultsPage({required this.api, required this.examId, super.key});
  final MobileApi api;
  final int examId;

  @override
  State<InstructorAssessmentResultsPage> createState() => _InstructorAssessmentResultsPageState();
}

class _InstructorAssessmentResultsPageState extends State<InstructorAssessmentResultsPage> {
  Map<String, dynamic>? payload;
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) { setState(() { loading = true; error = null; }); }
    try {
      final response = await widget.api.get('/instructor/exams/${widget.examId}/results');
      if (!mounted) { return; }
      setState(() => payload = response);
    } catch (exception) {
      if (mounted) { setState(() => error = exception.toString()); }
    } finally {
      if (mounted) { setState(() => loading = false); }
    }
  }

  @override
  Widget build(BuildContext context) {
    final exam = payload?['exam'] as Map<String, dynamic>? ?? {};
    final summary = payload?['summary'] as Map<String, dynamic>? ?? {};
    final attempts = payload?['data'] as List<dynamic>? ?? [];
    return Scaffold(
      appBar: AppBar(
        title: Text(exam['title']?.toString() ?? 'Assessment results'),
        actions: [IconButton(onPressed: loading ? null : load, icon: const Icon(Icons.refresh))],
      ),
      body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
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
                  Text('${exam['course'] ?? ''} · ${exam['batch'] ?? ''}'),
                  const SizedBox(height: 12),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    childAspectRatio: 1.8,
                    children: [
                      _AssessmentMetric(label: 'Submissions', value: '${summary['submitted_attempts'] ?? 0}'),
                      _AssessmentMetric(label: 'Passed', value: '${summary['passed_attempts'] ?? 0}'),
                      _AssessmentMetric(label: 'Pass rate', value: '${summary['pass_rate'] ?? 0}%'),
                      _AssessmentMetric(label: 'Average score', value: summary['average_score']?.toString() ?? '—'),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text('Student results', style: Theme.of(context).textTheme.titleLarge),
                  if (attempts.isEmpty) const ListTile(title: Text('No submitted attempts yet.')),
                  ...attempts.map((item) {
                    final attempt = item as Map<String, dynamic>;
                    final student = attempt['student'] as Map<String, dynamic>? ?? {};
                    final passed = attempt['passed'] == true;
                    return Card(child: ListTile(
                      leading: Icon(passed ? Icons.check_circle_outline : Icons.assignment_late_outlined,
                        color: passed ? Colors.green : Theme.of(context).colorScheme.error),
                      title: Text(student['name']?.toString() ?? 'Student'),
                      subtitle: Text('Score: ${attempt['score'] ?? 0} · ${passed ? 'Passed' : 'Not passed'}'),
                      trailing: Text(attempt['submitted_at']?.toString().split('T').first ?? ''),
                    ));
                  }),
                ],
              ),
            ),
    );
  }
}

class _AssessmentMetric extends StatelessWidget {
  const _AssessmentMetric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(value, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(label, textAlign: TextAlign.center),
        ],
      ),
    ),
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
  List<dynamic> batches = [];
  List<dynamic> assignments = [];
  List<dynamic> liveClasses = [];
  bool loading = true;
  String? error;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final responses = await Future.wait([
        widget.api.get('/instructor/courses'),
        widget.api.get('/instructor/batches'),
        widget.api.get('/instructor/assignments'),
        widget.api.get('/instructor/live-classes'),
      ]);
      if (mounted) {
        setState(() {
          courses = responses[0]['data'] as List<dynamic>? ?? [];
          batches = responses[1]['data'] as List<dynamic>? ?? [];
          assignments = responses[2]['data'] as List<dynamic>? ?? [];
          liveClasses = responses[3]['data'] as List<dynamic>? ?? [];
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => error = e.toString());
      }
    } finally {
      if (mounted) {
        setState(() => loading = false);
      }
    }
  }
  Future<Map<String, dynamic>?> editLessonDialog({Map<String, dynamic>? lesson, required int nextOrder}) async {
    final title = TextEditingController(text: lesson?['title']?.toString() ?? '');
    final content = TextEditingController(text: lesson?['content']?.toString() ?? '');
    final duration = TextEditingController(text: lesson?['duration_minutes']?.toString() ?? '15');
    final order = TextEditingController(text: lesson?['order']?.toString() ?? nextOrder.toString());
    final videoUrl = TextEditingController(text: lesson?['video_url']?.toString() ?? '');
    bool preview = lesson?['is_preview'] == true;
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setDialogState) => AlertDialog(
          title: Text(lesson == null ? 'Create lesson' : 'Edit lesson'),
          content: SizedBox(
            width: 480,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(controller: title, decoration: const InputDecoration(labelText: 'Lesson title')),
                  TextField(controller: content, minLines: 3, maxLines: 7, decoration: const InputDecoration(labelText: 'Lesson content / notes')),
                  TextField(controller: duration, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Duration (minutes)')),
                  TextField(controller: order, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Lesson order')),
                  TextField(controller: videoUrl, keyboardType: TextInputType.url, decoration: const InputDecoration(labelText: 'YouTube / Vimeo HTTPS URL (optional)')),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Free preview lesson'),
                    value: preview,
                    onChanged: (value) => setDialogState(() => preview = value),
                  ),
                  const Text('Video uploads and PDF attachments are managed from the secure web lesson editor.', style: TextStyle(fontSize: 12)),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
            FilledButton(
              onPressed: () {
                final minutes = int.tryParse(duration.text.trim());
                final position = int.tryParse(order.text.trim());
                if (title.text.trim().isEmpty || content.text.trim().isEmpty || minutes == null || minutes < 1 || minutes > 600 || position == null || position < 1) {
                  return;
                }
                Navigator.pop(dialogContext, {
                  'title': title.text.trim(),
                  'content': content.text.trim(),
                  'duration_minutes': minutes,
                  'order': position,
                  'video_url': videoUrl.text.trim(),
                  'is_preview': preview,
                });
              },
              child: Text(lesson == null ? 'Create' : 'Save changes'),
            ),
          ],
        ),
      ),
    );
    title.dispose();
    content.dispose();
    duration.dispose();
    order.dispose();
    videoUrl.dispose();
    return result;
  }

  Future<void> manageLessons(Map<String, dynamic> course) async {
    final slug = course['slug']?.toString();
    if (slug == null || slug.isEmpty) {
      return;
    }
    final navigator = Navigator.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final capturedThemes = InheritedTheme.capture(from: context, to: navigator.context);
    List<dynamic> lessons = [];
    bool busy = true;
    String? loadError;
    Future<void> refreshLessons(void Function(void Function()) update) async {
      update(() { busy = true; loadError = null; });
      try {
        final response = await widget.api.get('/instructor/courses/$slug/lessons');
        lessons = response['data'] as List<dynamic>? ?? [];
      } catch (e) {
        loadError = e.toString();
      } finally {
        update(() => busy = false);
      }
    }
    await refreshLessons(setState);
    if (!navigator.mounted) {
      return;
    }
    navigator.push<void>(ModalBottomSheetRoute<void>(
      isScrollControlled: true,
      capturedThemes: capturedThemes,
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, setSheetState) => SafeArea(
          child: SizedBox(
            height: MediaQuery.of(sheetContext).size.height * 0.82,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 8, 8),
                  child: Row(
                    children: [
                      Expanded(child: Text('Lessons — ${course['title']}', style: Theme.of(sheetContext).textTheme.titleLarge)),
                      FilledButton.icon(
                        onPressed: busy ? null : () async {
                          final payload = await editLessonDialog(nextOrder: lessons.length + 1);
                          if (payload == null) {
                            return;
                          }
                          try {
                            await widget.api.postJson('/instructor/courses/$slug/lessons', payload);
                            await refreshLessons(setSheetState);
                          } catch (e) {
                            if (messenger.mounted) {
                              messenger.showSnackBar(SnackBar(content: Text(e.toString())));
                            }
                          }
                        },
                        icon: const Icon(Icons.add),
                        label: const Text('Add'),
                      ),
                    ],
                  ),
                ),
                if (busy) const LinearProgressIndicator(),
                if (loadError != null) ListTile(title: Text(loadError!), trailing: IconButton(onPressed: () => refreshLessons(setSheetState), icon: const Icon(Icons.refresh))),
                Expanded(
                  child: ListView.builder(
                    itemCount: lessons.length,
                    itemBuilder: (context, index) {
                      final lesson = lessons[index] as Map<String, dynamic>;
                      final lessonId = lesson['id'];
                      return ListTile(
                        leading: CircleAvatar(child: Text('${lesson['order']}')),
                        title: Text(lesson['title']?.toString() ?? 'Lesson'),
                        subtitle: Text('${lesson['duration_minutes'] ?? 0} min · ${lesson['is_preview'] == true ? 'Preview' : 'Enrolled students'}'),
                        trailing: PopupMenuButton<String>(
                          onSelected: (action) async {
                            if (action == 'edit') {
                              final payload = await editLessonDialog(lesson: lesson, nextOrder: index + 1);
                              if (payload == null) {
                                return;
                              }
                              try {
                                await widget.api.putJson('/instructor/courses/$slug/lessons/$lessonId', payload);
                                await refreshLessons(setSheetState);
                              } catch (e) {
                                if (messenger.mounted) {
                              messenger.showSnackBar(SnackBar(content: Text(e.toString())));
                            }
                              }
                            } else {
                              if (!sheetContext.mounted) {
                                return;
                              }
                              final confirmed = await showDialog<bool>(
                                context: sheetContext,
                                builder: (dialogContext) => AlertDialog(
                                  title: const Text('Delete lesson?'),
                                  content: Text('“${lesson['title']}” will be permanently removed.'),
                                  actions: [
                                    TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
                                    FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Delete')),
                                  ],
                                ),
                              );
                              if (confirmed == true) {
                                try {
                                  await widget.api.deleteJson('/instructor/courses/$slug/lessons/$lessonId');
                                  await refreshLessons(setSheetState);
                                } catch (e) {
                                  if (messenger.mounted) {
                              messenger.showSnackBar(SnackBar(content: Text(e.toString())));
                            }
                                }
                              }
                            }
                          },
                          itemBuilder: (context) => const [
                            PopupMenuItem(value: 'edit', child: Text('Edit lesson')),
                            PopupMenuItem(value: 'delete', child: Text('Delete lesson')),
                          ],
                        ),
                      );
                    },
                  ),
                ),
                if (!busy && lessons.isEmpty && loadError == null) const Padding(padding: EdgeInsets.all(24), child: Text('No lessons yet. Add your first lesson.')),
              ],
            ),
          ),
        ),
      ),
    ));
  }

  Future<void> showStudents(Map<String, dynamic> course) async {
    final navigator = Navigator.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final titleStyle = Theme.of(context).textTheme.titleLarge;
    final capturedThemes = InheritedTheme.capture(from: context, to: navigator.context);
    try {
      final response = await widget.api.get("/instructor/courses/${course['slug']}/students");
      if (!navigator.mounted) {
        return;
      }
      final students = response['data'] as List<dynamic>? ?? [];
      navigator.push<void>(ModalBottomSheetRoute<void>(
        isScrollControlled: false,
        capturedThemes: capturedThemes,
        builder: (_) => SafeArea(child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('Students — ${course['title']}', style: titleStyle),
            ...students.map((item) {
              final student = item['student'] as Map<String, dynamic>;
              return ListTile(title: Text(student['name']?.toString() ?? 'Student'), subtitle: Text(student['email']?.toString() ?? ''));
            }),
            if (students.isEmpty) const ListTile(title: Text('No enrolled students yet.')),
          ],
        )),
      ));
    } catch (e) {
      if (!navigator.mounted || !messenger.mounted) {
        return;
      }
      if (e is ApiException && (e.statusCode == 401 || e.statusCode == 403)) {
        widget.onLogout();
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }
  Future<void> showBatchStudents(Map<String, dynamic> batch) async {
    final navigator = Navigator.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final capturedThemes = InheritedTheme.capture(from: context, to: navigator.context);
    try {
      final response = await widget.api.get('/instructor/batches/${batch['id']}');
      if (!navigator.mounted) {
        return;
      }
      final detail = response['batch'] as Map<String, dynamic>;
      final students = response['data'] as List<dynamic>? ?? [];
      navigator.push<void>(ModalBottomSheetRoute<void>(
        isScrollControlled: true,
        capturedThemes: capturedThemes,
        builder: (sheetContext) => SafeArea(
          child: SizedBox(
            height: MediaQuery.of(sheetContext).size.height * 0.75,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(detail['title']?.toString() ?? 'Batch', style: Theme.of(sheetContext).textTheme.titleLarge),
                Text('${detail['course'] ?? ''} · ${detail['status'] ?? ''}'),
                Text('Enrolled: ${detail['enrolled_count'] ?? 0} / ${detail['capacity'] ?? '—'}'),
                Text('Starts: ${detail['starts_at'] ?? '—'}'),
                Text('Ends: ${detail['ends_at'] ?? '—'}'),
                const Divider(),
                Text('Enrolled students', style: Theme.of(sheetContext).textTheme.titleMedium),
                ...students.map((item) {
                  final student = item['student'] as Map<String, dynamic>? ?? {};
                  return ListTile(
                    leading: const Icon(Icons.person_outline),
                    title: Text(student['name']?.toString() ?? 'Student'),
                    subtitle: Text([
                      student['email']?.toString() ?? '',
                      student['phone_number']?.toString() ?? '',
                    ].where((value) => value.isNotEmpty).join(' · ')),
                    trailing: Text(item['status']?.toString() ?? ''),
                  );
                }),
                if (students.isEmpty) const ListTile(title: Text('No active or completed students in this batch.')),
              ],
            ),
          ),
        ),
      ));
    } catch (e) {
      if (!navigator.mounted || !messenger.mounted) {
        return;
      }
      if (e is ApiException && (e.statusCode == 401 || e.statusCode == 403)) {
        widget.onLogout();
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> createAssignment() async {
    if (batches.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No assigned batches are available.')),
      );
      return;
    }
    final title = TextEditingController();
    final instructions = TextEditingController();
    final marks = TextEditingController(text: '100');
    int? selectedBatchId = (batches.first as Map<String, dynamic>)['id'] as int;
    DateTime dueAt = DateTime.now().add(const Duration(days: 2));
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogStateContext, setDialogState) => AlertDialog(
          title: const Text('Create assignment'),
          content: SingleChildScrollView(child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                initialValue: selectedBatchId,
                decoration: const InputDecoration(labelText: 'Batch'),
                items: batches.map((item) {
                  final batch = item as Map<String, dynamic>;
                  return DropdownMenuItem<int>(
                    value: batch['id'] as int,
                    child: Text(batch['title']?.toString() ?? 'Batch'),
                  );
                }).toList(),
                onChanged: (value) => setDialogState(() => selectedBatchId = value),
              ),
              TextField(controller: title, decoration: const InputDecoration(labelText: 'Assignment title')),
              TextField(
                controller: instructions,
                minLines: 3,
                maxLines: 5,
                decoration: const InputDecoration(labelText: 'Instructions'),
              ),
              TextField(
                controller: marks,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Maximum marks'),
              ),
              const SizedBox(height: 8),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Due date'),
                subtitle: Text(dueAt.toLocal().toString().split('.').first),
                trailing: IconButton(
                  icon: const Icon(Icons.calendar_month),
                  onPressed: () async {
                    final picked = await showDatePicker(
                      context: dialogStateContext,
                      initialDate: dueAt,
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)),
                    );
                    if (!dialogStateContext.mounted) {
                      return;
                    }
                    if (picked != null) {
                      setDialogState(() => dueAt = DateTime(
                        picked.year, picked.month, picked.day, 23, 59,
                      ));
                    }
                  },
                ),
              ),
            ],
          )),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
            FilledButton(
              onPressed: () {
                final parsedMarks = int.tryParse(marks.text.trim());
                if (selectedBatchId == null || title.text.trim().isEmpty ||
                    instructions.text.trim().isEmpty || parsedMarks == null || parsedMarks < 1) {
                  return;
                }
                Navigator.pop(dialogContext, {
                  'batch_id': selectedBatchId,
                  'title': title.text.trim(),
                  'instructions': instructions.text.trim(),
                  'max_marks': parsedMarks,
                  'due_at': dueAt.toIso8601String(),
                  'allow_late': false,
                });
              },
              child: const Text('Publish'),
            ),
          ],
        ),
      ),
    );
    title.dispose();
    instructions.dispose();
    marks.dispose();
    if (payload == null) {
      return;
    }
    try {
      await widget.api.postJson(
        '/instructor/batches/${payload.remove('batch_id')}/assignments',
        payload,
      );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Assignment published.')),
      );
      await load();
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<DateTime?> _pickDateTime(DateTime initial) async {
    final navigator = Navigator.of(context);
    final barrierLabel = MaterialLocalizations.of(context).modalBarrierDismissLabel;
    final date = await navigator.push<DateTime>(RawDialogRoute<DateTime>(
      pageBuilder: (_, __, ___) => DatePickerDialog(
        initialDate: initial,
        firstDate: DateTime.now(),
        lastDate: DateTime.now().add(const Duration(days: 365)),
      ),
      barrierDismissible: true,
      barrierLabel: barrierLabel,
      transitionDuration: const Duration(milliseconds: 200),
      transitionBuilder: (_, animation, __, child) => FadeTransition(opacity: animation, child: child),
    ));
    if (!navigator.mounted || date == null) {
      return null;
    }
    final time = await navigator.push<TimeOfDay>(RawDialogRoute<TimeOfDay>(
      pageBuilder: (_, __, ___) => TimePickerDialog(initialTime: TimeOfDay.fromDateTime(initial)),
      barrierDismissible: true,
      barrierLabel: barrierLabel,
      transitionDuration: const Duration(milliseconds: 200),
      transitionBuilder: (_, animation, __, child) => FadeTransition(opacity: animation, child: child),
    ));
    if (!navigator.mounted || time == null) {
      return null;
    }
    return DateTime(date.year, date.month, date.day, time.hour, time.minute);
  }

  Future<void> scheduleLiveClass() async {
    if (batches.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No assigned batches are available.')),
      );
      return;
    }
    final title = TextEditingController();
    final description = TextEditingController();
    final meetingUrl = TextEditingController();
    int? selectedBatchId = (batches.first as Map<String, dynamic>)['id'] as int;
    DateTime startsAt = DateTime.now().add(const Duration(days: 1));
    startsAt = DateTime(startsAt.year, startsAt.month, startsAt.day, 19);
    DateTime endsAt = startsAt.add(const Duration(hours: 1));
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Schedule live class'),
          content: SingleChildScrollView(child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                initialValue: selectedBatchId,
                decoration: const InputDecoration(labelText: 'Batch'),
                items: batches.map((item) {
                  final batch = item as Map<String, dynamic>;
                  return DropdownMenuItem<int>(
                    value: batch['id'] as int,
                    child: Text(batch['title']?.toString() ?? 'Batch'),
                  );
                }).toList(),
                onChanged: (value) => setDialogState(() => selectedBatchId = value),
              ),
              TextField(controller: title, decoration: const InputDecoration(labelText: 'Session title')),
              TextField(
                controller: description,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(labelText: 'Description (optional)'),
              ),
              TextField(
                controller: meetingUrl,
                keyboardType: TextInputType.url,
                decoration: const InputDecoration(labelText: 'HTTPS meeting URL'),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Starts'),
                subtitle: Text(startsAt.toLocal().toString().split('.').first),
                trailing: IconButton(
                  icon: const Icon(Icons.calendar_month),
                  onPressed: () async {
                    final picked = await _pickDateTime(startsAt);
                    if (picked != null) {
                      setDialogState(() {
                        startsAt = picked;
                        if (!endsAt.isAfter(startsAt)) {
                          endsAt = startsAt.add(const Duration(hours: 1));
                        }
                      });
                    }
                  },
                ),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Ends'),
                subtitle: Text(endsAt.toLocal().toString().split('.').first),
                trailing: IconButton(
                  icon: const Icon(Icons.schedule),
                  onPressed: () async {
                    final picked = await _pickDateTime(endsAt);
                    if (picked != null) {
                      setDialogState(() => endsAt = picked);
                    }
                  },
                ),
              ),
            ],
          )),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
            FilledButton(
              onPressed: () {
                final url = meetingUrl.text.trim();
                if (selectedBatchId == null || title.text.trim().isEmpty ||
                    !url.startsWith('https://') || !endsAt.isAfter(startsAt) ||
                    !startsAt.isAfter(DateTime.now())) {
                  return;
                }
                Navigator.pop(dialogContext, {
                  'batch_id': selectedBatchId,
                  'title': title.text.trim(),
                  'description': description.text.trim(),
                  'meeting_url': url,
                  'starts_at': startsAt.toUtc().toIso8601String(),
                  'ends_at': endsAt.toUtc().toIso8601String(),
                });
              },
              child: const Text('Schedule'),
            ),
          ],
        ),
      ),
    );
    title.dispose();
    description.dispose();
    meetingUrl.dispose();
    if (payload == null || !mounted) {
      return;
    }
    try {
      final batchId = payload.remove('batch_id');
      await widget.api.postJson('/instructor/batches/$batchId/live-classes', payload);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Live class scheduled.')),
      );
      await load();
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> updateLiveClass(
    Map<String, dynamic> liveClass, {
    String? status,
    String? recordingUrl,
  }) async {
    final payload = <String, dynamic>{};
    if (status != null) {
      payload['status'] = status;
    }
    if (recordingUrl != null) {
      payload['recording_url'] = recordingUrl.trim().isEmpty ? null : recordingUrl.trim();
    }
    try {
      await widget.api.patchJson('/instructor/live-classes/${liveClass['id']}', payload);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Live class updated.')),
      );
      await load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  Future<void> publishRecording(Map<String, dynamic> liveClass) async {
    final controller = TextEditingController(
      text: liveClass['recording_url']?.toString() ?? '',
    );
    final url = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Publish class recording'),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.url,
          decoration: const InputDecoration(
            labelText: 'YouTube or Vimeo HTTPS URL',
            hintText: 'https://youtu.be/...',
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, controller.text.trim()),
            child: const Text('Publish'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (url == null) {
      return;
    }
    if (!mounted) {
      return;
    }
    if (url.isNotEmpty && !url.startsWith('https://')) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Recording URL must use HTTPS.')),
      );
      return;
    }
    await updateLiveClass(liveClass, recordingUrl: url);
  }

  Future<void> viewSubmissions(Map<String, dynamic> assignment) async {
    try {
      final response = await widget.api.get(
        '/instructor/assignments/${assignment['id']}/submissions',
      );
      if (!mounted) {
        return;
      }
      final submissions = response['data'] as List<dynamic>? ?? [];
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (sheetContext) => SafeArea(
          child: SizedBox(
            height: MediaQuery.of(sheetContext).size.height * 0.75,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  'Submissions — ${assignment['title']}',
                  style: Theme.of(sheetContext).textTheme.titleLarge,
                ),
                if (submissions.isEmpty)
                  const ListTile(title: Text('No submissions received yet.')),
                ...submissions.map((raw) {
                  final item = raw as Map<String, dynamic>;
                  final student = item['student'] as Map<String, dynamic>? ?? {};
                  return Card(child: ListTile(
                    title: Text(student['name']?.toString() ?? 'Student'),
                    subtitle: Text(
                      '${item['status']} · ${item['submitted_at'] ?? ''} · Score: ${item['score'] ?? '—'}',
                    ),
                    trailing: const Icon(Icons.rate_review_outlined),
                    onTap: () {
                      Navigator.pop(sheetContext);
                      gradeSubmission(assignment, item);
                    },
                  ));
                }),
              ],
            ),
          ),
        ),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> gradeSubmission(
    Map<String, dynamic> assignment,
    Map<String, dynamic> submission,
  ) async {
    final score = TextEditingController(
      text: submission['score']?.toString() ?? '',
    );
    final feedback = TextEditingController(
      text: submission['feedback']?.toString() ?? '',
    );
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text('Grade — ${submission['student']?['name'] ?? 'Student'}'),
        content: SingleChildScrollView(child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(submission['answer']?.toString() ?? 'No text answer attached.'),
            const SizedBox(height: 12),
            TextField(
              controller: score,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                labelText: 'Score (max ${assignment['max_marks']})',
              ),
            ),
            TextField(
              controller: feedback,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Feedback'),
            ),
          ],
        )),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
          FilledButton(
            onPressed: () {
              final value = num.tryParse(score.text.trim());
              if (value == null || value < 0 || value > (assignment['max_marks'] as num)) {
                return;
              }
              Navigator.pop(dialogContext, {'score': value, 'feedback': feedback.text.trim()});
            },
            child: const Text('Save grade'),
          ),
        ],
      ),
    );
    score.dispose();
    feedback.dispose();
    if (payload == null || !mounted) {
      return;
    }
    try {
      await widget.api.patchJson(
        '/instructor/assignments/${assignment['id']}/submissions/${submission['id']}',
        payload,
      );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Grade saved successfully.')),
      );
      await load();
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> openAssessments() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => InstructorAssessmentsPage(api: widget.api),
    ));
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
              trailing: PopupMenuButton<String>(
                tooltip: 'Course actions',
                onSelected: (action) {
                  if (action == 'lessons') {
                    manageLessons(course);
                  } else {
                    showStudents(course);
                  }
                },
                itemBuilder: (context) => const [
                  PopupMenuItem(value: 'students', child: Text('View students')),
                  PopupMenuItem(value: 'lessons', child: Text('Manage lessons')),
                ],
              ),
              onTap: () => showStudents(course),
            ));
          }),
          const SizedBox(height: 24),
          Text('My Batches', style: Theme.of(context).textTheme.titleLarge),
          if (batches.isEmpty) const ListTile(title: Text('No batches assigned.')),
          ...batches.map((item) {
            final batch = item as Map<String, dynamic>;
            return Card(child: ListTile(
              leading: const Icon(Icons.groups_outlined),
              title: Text(batch['title']?.toString() ?? 'Batch'),
              subtitle: Text('${batch['course'] ?? ''} · ${batch['status'] ?? ''} · ${batch['assignments_count'] ?? 0} assignments'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => showBatchStudents(batch),
            ));
          }),
          const SizedBox(height: 16),
          Card(child: ListTile(
            leading: const Icon(Icons.fact_check_outlined),
            title: const Text('Attendance & Offline Sync'),
            subtitle: const Text('Mark attendance with foreground location and sync queued records.'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => InstructorAttendancePage(api: widget.api),
            )),
          )),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(child: Text('Assignments', style: Theme.of(context).textTheme.titleLarge)),
              FilledButton.icon(
                onPressed: createAssignment,
                icon: const Icon(Icons.add),
                label: const Text('Create'),
              ),
            ],
          ),
          if (assignments.isEmpty) const ListTile(title: Text('No assignments published yet.')),
          ...assignments.map((item) {
            final assignment = item as Map<String, dynamic>;
            return Card(child: ListTile(
              leading: const Icon(Icons.assignment_outlined),
              title: Text(assignment['title']?.toString() ?? 'Assignment'),
              subtitle: Text(
                '${assignment['batch'] ?? 'Batch'} · ${assignment['submissions_count'] ?? 0} submissions · Due ${assignment['due_at'] ?? '—'}',
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => viewSubmissions(assignment),
            ));
          }),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(child: Text('Assessments & Results', style: Theme.of(context).textTheme.titleLarge)),
              OutlinedButton.icon(
                onPressed: openAssessments,
                icon: const Icon(Icons.analytics_outlined),
                label: const Text('View results'),
              ),
            ],
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(child: Text('Live Classes', style: Theme.of(context).textTheme.titleLarge)),
              FilledButton.icon(
                onPressed: scheduleLiveClass,
                icon: const Icon(Icons.video_call),
                label: const Text('Schedule'),
              ),
            ],
          ),
          if (liveClasses.isEmpty) const ListTile(title: Text('No live classes scheduled.')),
          ...liveClasses.map((item) {
            final liveClass = item as Map<String, dynamic>;
            return Card(child: ListTile(
              leading: const Icon(Icons.video_camera_front_outlined),
              title: Text(liveClass['title']?.toString() ?? 'Live class'),
              subtitle: Text(
                '${liveClass['batch'] ?? 'Batch'} · ${liveClass['status'] ?? ''} · ${liveClass['starts_at'] ?? ''}',
              ),
              isThreeLine: true,
              trailing: PopupMenuButton<String>(
                tooltip: 'Manage live class',
                onSelected: (value) {
                  if (value == 'recording') {
                    publishRecording(liveClass);
                  } else {
                    updateLiveClass(liveClass, status: value);
                  }
                },
                itemBuilder: (context) => [
                  const PopupMenuItem(value: 'scheduled', child: Text('Mark scheduled')),
                  const PopupMenuItem(value: 'live', child: Text('Mark live')),
                  const PopupMenuItem(value: 'completed', child: Text('Mark completed')),
                  const PopupMenuItem(value: 'cancelled', child: Text('Cancel class')),
                  const PopupMenuDivider(),
                  const PopupMenuItem(value: 'recording', child: Text('Publish recording')),
                ],
              ),
            ));
          }),
        ]),
    ),
  );
}
