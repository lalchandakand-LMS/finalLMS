import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:geolocator/geolocator.dart';
import 'package:qr_flutter/qr_flutter.dart';

import 'api_client.dart';
import 'attendance_offline_queue.dart';

class InstructorAttendancePage extends StatefulWidget {
  const InstructorAttendancePage({required this.api, super.key});

  final MobileApi api;

  @override
  State<InstructorAttendancePage> createState() => _InstructorAttendancePageState();
}

class _InstructorAttendancePageState extends State<InstructorAttendancePage>
    with WidgetsBindingObserver {
  final AttendanceOfflineQueue queue = AttendanceOfflineQueue();
  List<dynamic> batches = [];
  List<dynamic> students = [];
  List<AttendanceQueueEntry> records = [];
  int? selectedBatchId;
  bool loading = true;
  bool loadingStudents = false;
  bool capturing = false;
  bool syncing = false;
  bool creatingQr = false;
  bool allowExcused = false;
  int maxLocationAgeMinutes = 10;
  StreamSubscription<List<ConnectivityResult>>? connectivitySubscription;
  String? error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    connectivitySubscription = Connectivity().onConnectivityChanged.listen((results) {
      if (mounted && results.any((result) => result != ConnectivityResult.none)) {
        syncQueue();
      }
    });
    load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    connectivitySubscription?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      refreshQueue();
      syncQueue();
    }
  }

  Future<void> load() async {
    setState(() { loading = true; error = null; });
    try {
      final response = await widget.api.get('/instructor/batches');
      final policy = await widget.api.get('/instructor/attendance/policy');
      final values = response['data'] as List<dynamic>? ?? [];
      if (!mounted) return;
      setState(() {
        batches = values;
        allowExcused = policy['allow_excused'] == true;
        maxLocationAgeMinutes = (policy['max_location_age_minutes'] as num?)?.toInt() ?? 10;
        selectedBatchId ??= values.isNotEmpty
            ? (values.first as Map<String, dynamic>)['id'] as int
            : null;
      });
      await refreshQueue();
      if (selectedBatchId != null) await loadStudents(selectedBatchId!);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> refreshQueue() async {
    try {
      final values = await queue.all();
      if (mounted) setState(() => records = values);
    } catch (exception) {
      if (mounted) {
        setState(() => error = 'Could not read the encrypted queue: $exception');
      }
    }
  }

  Future<void> loadStudents(int batchId) async {
    setState(() { loadingStudents = true; error = null; });
    try {
      final response = await widget.api.get('/instructor/batches/$batchId');
      if (!mounted) {
        return;
      }
      setState(() => students = response['data'] as List<dynamic>? ?? []);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loadingStudents = false);
    }
  }

  Future<Position> captureForegroundLocation() async {
    final enabled = await Geolocator.isLocationServiceEnabled();
    if (!enabled) {
      throw StateError('Turn on device location services before marking attendance.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw StateError('Foreground location permission is required for attendance.');
    }
    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 20),
      ),
    );
  }

  Future<void> markStudent(Map<String, dynamic> student, String status) async {
    final batchId = selectedBatchId;
    final studentId = (student['id'] as num?)?.toInt();
    if (batchId == null || studentId == null) return;
    setState(() { capturing = true; error = null; });
    try {
      final position = await captureForegroundLocation();
      await queue.enqueue(
        batchId: batchId,
        studentId: studentId,
        attendanceDate: DateTime.now().toIso8601String().substring(0, 10),
        status: status,
        latitude: position.latitude,
        longitude: position.longitude,
        accuracyMeters: position.accuracy,
        capturedAt: position.timestamp,
        maxLocationAgeMinutes: maxLocationAgeMinutes,
      );
      await refreshQueue();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Attendance queued for ${student['name'] ?? 'student'}; sync when online.')),
      );
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => capturing = false);
    }
  }

  Future<void> syncQueue() async {
    if (syncing || !mounted) return;
    setState(() { syncing = true; error = null; });
    try {
      await widget.api.syncAttendanceQueue(queue);
      await refreshQueue();
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => syncing = false);
    }
  }

  Future<void> resolveConflict(AttendanceQueueEntry entry) async {
    final attendanceId = entry.serverAttendanceId;
    if (attendanceId == null) {
      setState(() => error = 'The server did not provide an attendance reference. Sync this record again.');
      return;
    }
    final reason = TextEditingController();
    String selectedStatus = entry.status;
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setDialogState) => AlertDialog(
          title: const Text('Resolve attendance conflict'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            DropdownButtonFormField<String>(
              initialValue: selectedStatus,
              decoration: const InputDecoration(labelText: 'Correct attendance status'),
              items: const [
                DropdownMenuItem(value: 'present', child: Text('Present')),
                DropdownMenuItem(value: 'absent', child: Text('Absent')),
                DropdownMenuItem(value: 'late', child: Text('Late')),
              ],
              onChanged: (value) {
                if (value != null) setDialogState(() => selectedStatus = value);
              },
            ),
            TextField(
              controller: reason,
              onChanged: (_) => setDialogState(() {}),
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Reason (required)',
                hintText: 'Explain how this correction was verified',
              ),
            ),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
            FilledButton(
              onPressed: reason.text.trim().length < 5 ? null : () => Navigator.pop(dialogContext, {
                'status': selectedStatus,
                'reason': reason.text.trim(),
              }),
              child: const Text('Save resolution'),
            ),
          ],
        ),
      ),
    );
    reason.dispose();
    if (payload == null || !mounted) return;
    try {
      await widget.api.patchJson('/instructor/attendance/$attendanceId/resolve', payload);
      await queue.markConflictResolved(entry.idempotencyKey);
      await refreshQueue();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Conflict resolved and audit log updated.')),
        );
      }
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    }
  }

  Future<void> showStudentQrCode() async {
    final batchId = selectedBatchId;
    if (batchId == null || creatingQr) return;
    setState(() { creatingQr = true; error = null; });
    try {
      final response = await widget.api.postJson(
        '/instructor/batches/$batchId/attendance/qr',
        {'attendance_date': DateTime.now().toIso8601String().substring(0, 10)},
      );
      final data = response['data'] as Map<String, dynamic>? ?? {};
      final checkInUrl = data['check_in_url']?.toString() ?? '';
      final expiresAt = data['expires_at']?.toString() ?? '';
      if (checkInUrl.isEmpty || expiresAt.isEmpty) {
        throw StateError('The server did not return a valid attendance QR session.');
      }
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Student attendance QR'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              QrImageView(
                data: checkInUrl,
                version: QrVersions.auto,
                size: 240,
                backgroundColor: Colors.white,
                errorCorrectionLevel: QrErrorCorrectLevel.H,
              ),
              const SizedBox(height: 12),
              const Text(
                'Students must scan this code in the Student app while connected to the internet and with location enabled.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                'Expires: $expiresAt',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 8),
              const Text(
                'This QR code is single-session and expires within 120 seconds. Generate a new one for the next session.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Close'),
            ),
          ],
        ),
      );
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => creatingQr = false);
    }
  }

  Future<void> retryFailed() async {
    await queue.retryFailed();
    await refreshQueue();
  }

  Future<void> pruneSynced() async {
    await queue.pruneSynced();
    await refreshQueue();
  }

  String statusLabel(AttendanceSyncState state) => switch (state) {
    AttendanceSyncState.pending => 'Pending',
    AttendanceSyncState.synced => 'Synced',
    AttendanceSyncState.conflict => 'Needs review',
    AttendanceSyncState.failed => 'Failed',
    AttendanceSyncState.review => 'Awaiting admin review',
  };

  @override
  Widget build(BuildContext context) {
    final pending = records.where((entry) => entry.syncState == AttendanceSyncState.pending).length;
    final failed = records.where((entry) => entry.syncState == AttendanceSyncState.failed).length;
    final review = records.where((entry) => entry.syncState == AttendanceSyncState.review).length;
    final conflicts = records.where((entry) => entry.syncState == AttendanceSyncState.conflict).length;
    final synced = records.where((entry) => entry.syncState == AttendanceSyncState.synced).length;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Attendance'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: loading ? null : load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null && batches.isEmpty
              ? Center(child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Text(error!),
                    FilledButton(onPressed: load, child: const Text('Retry')),
                  ]),
                ))
              : RefreshIndicator(
                  onRefresh: load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (error != null) MaterialBanner(
                        content: Text(error!),
                        actions: [TextButton(onPressed: () => setState(() => error = null), child: const Text('Dismiss'))],
                      ),
                      Text('Select assigned batch', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      DropdownButtonFormField<int>(
                        initialValue: selectedBatchId,
                        decoration: const InputDecoration(border: OutlineInputBorder()),
                        items: batches.map((item) {
                          final batch = item as Map<String, dynamic>;
                          return DropdownMenuItem<int>(
                            value: (batch['id'] as num).toInt(),
                            child: Text(batch['title']?.toString() ?? 'Batch'),
                          );
                        }).toList(),
                        onChanged: loadingStudents ? null : (value) {
                          if (value == null) return;
                          setState(() { selectedBatchId = value; students = []; });
                          loadStudents(value);
                        },
                      ),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          onPressed: selectedBatchId == null || creatingQr || loadingStudents
                              ? null
                              : showStudentQrCode,
                          icon: creatingQr
                              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Icon(Icons.qr_code_2),
                          label: Text(creatingQr ? 'Creating QR session…' : 'Show student QR check-in'),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Card(child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('Offline queue', style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 8),
                          Wrap(spacing: 14, runSpacing: 8, children: [
                            Text('Pending: $pending'),
                            Text('Synced: $synced'),
                            Text('Failed: $failed'),
                            Text('Conflicts: $conflicts'),
                            Text('Admin review: $review'),
                          ]),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [
                            FilledButton.icon(
                              onPressed: syncing || (pending + review) == 0 ? null : syncQueue,
                              icon: syncing
                                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Icon(Icons.sync),
                              label: const Text('Sync now'),
                            ),
                            OutlinedButton(
                              onPressed: failed == 0 ? null : retryFailed,
                              child: const Text('Retry failed'),
                            ),
                            TextButton(
                              onPressed: synced == 0 ? null : pruneSynced,
                              child: const Text('Clear synced'),
                            ),
                          ]),
                        ]),
                      )),
                      const SizedBox(height: 16),
                      Row(children: [
                        Expanded(child: Text('Student roster', style: Theme.of(context).textTheme.titleLarge)),
                        if (capturing) const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
                      ]),
                      const SizedBox(height: 8),
                      if (loadingStudents) const LinearProgressIndicator(),
                      if (!loadingStudents && students.isEmpty)
                        const ListTile(title: Text('No enrolled students found for this batch.')),
                      ...students.map((item) {
                        final enrollment = item as Map<String, dynamic>;
                        final student = enrollment['student'] as Map<String, dynamic>? ?? {};
                        final name = student['name']?.toString() ?? 'Student';
                        return Card(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 12),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(name, style: Theme.of(context).textTheme.titleSmall),
                              Text(enrollment['status']?.toString() ?? ''),
                              const SizedBox(height: 6),
                              Wrap(spacing: 8, children: [
                                for (final status in ['present', 'absent', 'late', if (allowExcused) 'excused'])
                                  OutlinedButton(
                                    onPressed: capturing || loadingStudents
                                        ? null
                                        : () => markStudent(student, status),
                                    child: Text(status[0].toUpperCase() + status.substring(1)),
                                  ),
                              ]),
                            ]),
                          ),
                        );
                      }),
                      const SizedBox(height: 16),
                      Text('Recent queued records', style: Theme.of(context).textTheme.titleMedium),
                      if (records.isEmpty) const ListTile(title: Text('No offline attendance records yet.')),
                      ...records.reversed.take(20).map((entry) => ListTile(
                        dense: true,
                        leading: Icon(
                          entry.syncState == AttendanceSyncState.synced
                              ? Icons.check_circle_outline
                              : entry.syncState == AttendanceSyncState.conflict
                                  ? Icons.warning_amber_outlined
                                  : entry.syncState == AttendanceSyncState.failed
                                      ? Icons.error_outline
                                      : entry.syncState == AttendanceSyncState.review
                                          ? Icons.admin_panel_settings_outlined
                                          : Icons.cloud_upload_outlined,
                        ),
                        title: Text('Student #${entry.studentId} · ${entry.status}'),
                        subtitle: Text('${entry.attendanceDate} · ${statusLabel(entry.syncState)}${entry.lastError == null ? '' : ' · ${entry.lastError}'}'),
                        trailing: entry.syncState == AttendanceSyncState.conflict
                            ? TextButton(onPressed: syncing ? null : () => resolveConflict(entry), child: const Text('Resolve'))
                            : null,
                      )),
                    ],
                  ),
                ),
    );
  }
}
