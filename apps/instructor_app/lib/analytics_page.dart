import 'package:material_ui/material_ui.dart';

import 'api_client.dart';

class InstructorAnalyticsPage extends StatefulWidget {
  const InstructorAnalyticsPage({required this.api, super.key});

  final MobileApi api;

  @override
  State<InstructorAnalyticsPage> createState() => _InstructorAnalyticsPageState();
}

class _InstructorAnalyticsPageState extends State<InstructorAnalyticsPage> {
  Map<String, dynamic>? summary;
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) {
      setState(() {
        loading = true;
        error = null;
      });
    }

    try {
      final response = await widget.api.get('/instructor/analytics/summary');
      if (!mounted) return;
      final data = response['data'];
      if (data is! Map<String, dynamic>) {
        throw const FormatException('Analytics response is incomplete.');
      }
      setState(() => summary = data);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  int _number(Object? value) => value is num ? value.toInt() : 0;

  String _percent(Object? value) {
    if (value is! num) return '—';
    return '${value.toStringAsFixed(1)}%';
  }

  @override
  Widget build(BuildContext context) {
    final attendance = summary?['attendance'] as Map<String, dynamic>? ?? {};
    final assignments = summary?['assignments'] as Map<String, dynamic>? ?? {};
    final assessments = summary?['assessments'] as Map<String, dynamic>? ?? {};

    return Scaffold(
      appBar: AppBar(
        title: const Text('Academic analytics'),
        actions: [
          IconButton(
            tooltip: 'Refresh analytics',
            onPressed: loading ? null : load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.cloud_off_outlined, size: 40),
                        const SizedBox(height: 12),
                        Text(error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        FilledButton.icon(
                          onPressed: load,
                          icon: const Icon(Icons.refresh),
                          label: const Text('Try again'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(
                        'Your teaching overview',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Only records from your assigned batches and assessments are included.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      const SizedBox(height: 16),
                      GridView.count(
                        crossAxisCount: MediaQuery.of(context).size.width >= 600 ? 4 : 2,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        childAspectRatio: 1.65,
                        crossAxisSpacing: 8,
                        mainAxisSpacing: 8,
                        children: [
                          _MetricCard(label: 'My batches', value: '${_number(summary?['batches_count'])}', icon: Icons.groups_outlined),
                          _MetricCard(label: 'Active students', value: '${_number(summary?['active_students_count'])}', icon: Icons.school_outlined),
                          _MetricCard(label: 'Assignments', value: '${_number(assignments['published'])}', icon: Icons.assignment_outlined),
                          _MetricCard(label: 'Assessments', value: '${_number(assessments['published'])}', icon: Icons.quiz_outlined),
                        ],
                      ),
                      const SizedBox(height: 20),
                      _SectionCard(
                        title: 'Attendance',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _percent(attendance['attendance_rate']),
                              style: Theme.of(context).textTheme.headlineMedium,
                            ),
                            const Text('Present rate across recorded attendance'),
                            const SizedBox(height: 12),
                            Wrap(
                              spacing: 16,
                              runSpacing: 8,
                              children: [
                                _CountLabel(label: 'Present', value: _number(attendance['present'])),
                                _CountLabel(label: 'Absent', value: _number(attendance['absent'])),
                                _CountLabel(label: 'Late', value: _number(attendance['late'])),
                                _CountLabel(label: 'Excused', value: _number(attendance['excused'])),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text('Total records: ${_number(attendance['total_records'])}'),
                          ],
                        ),
                      ),
                      _SectionCard(
                        title: 'Assignments',
                        child: Wrap(
                          spacing: 24,
                          runSpacing: 12,
                          children: [
                            _CountLabel(label: 'Submissions', value: _number(assignments['submissions'])),
                            _CountLabel(label: 'Graded', value: _number(assignments['graded'])),
                          ],
                        ),
                      ),
                      _SectionCard(
                        title: 'Assessment performance',
                        child: Wrap(
                          spacing: 24,
                          runSpacing: 12,
                          children: [
                            _CountLabel(label: 'Submitted attempts', value: _number(assessments['submitted_attempts'])),
                            _CountLabel(label: 'Graded attempts', value: _number(assessments['graded_attempts'])),
                            _CountLabel(label: 'Passed attempts', value: _number(assessments['passed_attempts'])),
                            _CountLabel(label: 'Pass rate', value: _percent(assessments['pass_rate'])),
                            _CountLabel(
                              label: 'Average score',
                              value: assessments['average_score'] is num
                                  ? (assessments['average_score'] as num).toStringAsFixed(2)
                                  : '—',
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Statistics update from the server. Draft assignments and assessments are excluded.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon),
              const SizedBox(height: 6),
              Text(value, style: Theme.of(context).textTheme.titleLarge),
              Text(label, textAlign: TextAlign.center),
            ],
          ),
        ),
      );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
        margin: const EdgeInsets.only(bottom: 12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              child,
            ],
          ),
        ),
      );
}

class _CountLabel extends StatelessWidget {
  const _CountLabel({required this.label, required this.value});

  final String label;
  final Object value;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('$value', style: Theme.of(context).textTheme.titleMedium),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      );
}
