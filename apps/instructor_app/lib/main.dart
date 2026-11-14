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
  bool busy = false;
  String? error;
  @override
  void dispose() { email.dispose(); password.dispose(); super.dispose(); }
  Future<void> submit() async {
    setState(() { busy = true; error = null; });
    try {
      await widget.api.login(email.text.trim(), password.text, 'Aloms Instructor App');
      widget.onLogin();
    } catch (e) {
      if (mounted) {
        setState(() => error = e.toString());
      }
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
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
      if (e is ApiException && (e.statusCode == 401 || e.statusCode == 403)) {
        widget.onLogout();
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
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
        builder: (context, setDialogState) => AlertDialog(
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
                      context: context,
                      initialDate: dueAt,
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)),
                    );
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
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  Future<DateTime?> _pickDateTime(DateTime initial) async {
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) {\n      return null;\n    }
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null) {\n      return null;\n    }
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
                    if (picked != null) {\n                      setDialogState(() {
                      startsAt = picked;
                      if (!endsAt.isAfter(startsAt)) {\n                        endsAt = startsAt.add(const Duration(hours: 1));\n                      }
                    });
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
                    if (picked != null) {\n                      setDialogState(() => endsAt = picked);\n                    }
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
    if (payload == null) {\n      return;\n    }
    try {
      final batchId = payload.remove('batch_id');
      await widget.api.postJson('/instructor/batches/$batchId/live-classes', payload);
      if (!mounted) {\n        return;\n      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Live class scheduled.')),
      );
      await load();
    } catch (e) {
      if (mounted) {\n        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));\n      }
    }
  }

  Future<void> updateLiveClass(
    Map<String, dynamic> liveClass, {
    String? status,
    String? recordingUrl,
  }) async {
    final payload = <String, dynamic>{};
    if (status != null) payload['status'] = status;
    if (recordingUrl != null) {
      payload['recording_url'] = recordingUrl.trim().isEmpty ? null : recordingUrl.trim();
    }
    try {
      await widget.api.patchJson('/instructor/live-classes/${liveClass['id']}', payload);
      if (!mounted) {\n      return;\n    }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Live class updated.')),
      );
      await load();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
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
    if (url == null) {\n      return;\n    }
    if (!url.isEmpty && !url.startsWith('https://')) {
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
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
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
    if (payload == null) {
      return;
    }
    try {
      await widget.api.patchJson(
        '/instructor/assignments/${assignment['id']}/submissions/${submission['id']}',
        payload,
      );
      if (!mounted) {\n        return;\n      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Grade saved successfully.')),
      );
      await load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
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
