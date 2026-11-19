import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// One locally captured attendance operation.
///
/// The bearer token is deliberately not part of this model or its serialized
/// representation. Authentication is added only when the API client sends a
/// sync request.
class AttendanceQueueEntry {
  const AttendanceQueueEntry({
    required this.idempotencyKey,
    required this.batchId,
    required this.studentId,
    required this.attendanceDate,
    required this.status,
    required this.latitude,
    required this.longitude,
    required this.accuracyMeters,
    required this.capturedAt,
    this.syncState = AttendanceSyncState.pending,
    this.lastError,
    this.serverAttendanceId,
    this.serverReviewId,
  });

  final String idempotencyKey;
  final int batchId;
  final int studentId;
  final String attendanceDate;
  final String status;
  final double latitude;
  final double longitude;
  final double accuracyMeters;
  final DateTime capturedAt;
  final AttendanceSyncState syncState;
  final String? lastError;
  final int? serverAttendanceId;
  final int? serverReviewId;

  Map<String, dynamic> toJson() => {
        'idempotency_key': idempotencyKey,
        'batch_id': batchId,
        'student_id': studentId,
        'attendance_date': attendanceDate,
        'status': status,
        'location': {
          'latitude': latitude,
          'longitude': longitude,
          'accuracy_meters': accuracyMeters,
          'captured_at': capturedAt.toUtc().toIso8601String(),
        },
        'sync_state': syncState.name,
        if (lastError != null) 'last_error': lastError,
        if (serverAttendanceId != null) 'server_attendance_id': serverAttendanceId,
        if (serverReviewId != null) 'server_review_id': serverReviewId,
      };

  factory AttendanceQueueEntry.fromJson(Map<String, dynamic> json) {
    final location = json['location'] as Map<String, dynamic>;
    return AttendanceQueueEntry(
      idempotencyKey: json['idempotency_key'] as String,
      batchId: (json['batch_id'] as num).toInt(),
      studentId: (json['student_id'] as num).toInt(),
      attendanceDate: json['attendance_date'] as String,
      status: json['status'] as String,
      latitude: (location['latitude'] as num).toDouble(),
      longitude: (location['longitude'] as num).toDouble(),
      accuracyMeters: (location['accuracy_meters'] as num).toDouble(),
      capturedAt: DateTime.parse(location['captured_at'] as String).toUtc(),
      syncState: AttendanceSyncState.values.firstWhere(
        (value) => value.name == json['sync_state'],
        orElse: () => AttendanceSyncState.pending,
      ),
      lastError: json['last_error'] as String?,
      serverAttendanceId: (json['server_attendance_id'] as num?)?.toInt(),
      serverReviewId: (json['server_review_id'] as num?)?.toInt(),
    );
  }

  AttendanceQueueEntry withSyncState(
    AttendanceSyncState state, {
    String? error,
    int? serverAttendanceId,
    int? serverReviewId,
  }) =>
      AttendanceQueueEntry(
        idempotencyKey: idempotencyKey,
        batchId: batchId,
        studentId: studentId,
        attendanceDate: attendanceDate,
        status: status,
        latitude: latitude,
        longitude: longitude,
        accuracyMeters: accuracyMeters,
        capturedAt: capturedAt,
        syncState: state,
        lastError: error,
        serverAttendanceId: serverAttendanceId ?? this.serverAttendanceId,
        serverReviewId: serverReviewId ?? this.serverReviewId,
      );
}

enum AttendanceSyncState { pending, synced, conflict, failed, review }

abstract interface class AttendanceQueueStorage {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

/// Uses the platform encrypted key store supplied by flutter_secure_storage.
class SecureAttendanceQueueStorage implements AttendanceQueueStorage {
  SecureAttendanceQueueStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}

/// Encrypted, bounded offline attendance queue for the instructor app.
///
/// Queue records contain academic data and foreground location metadata only.
/// Access tokens are never persisted in the queue; the authenticated API client
/// must attach its current token at request time.
class AttendanceOfflineQueue {
  AttendanceOfflineQueue({required int ownerId, AttendanceQueueStorage? storage})
      : assert(ownerId > 0),
        _ownerId = ownerId,
        _storage = storage ?? SecureAttendanceQueueStorage();

  // Each instructor account gets a separate encrypted queue. A second person
  // signing in on the same device must never inherit another account's pending
  // attendance or upload it using their own bearer token.
  final int _ownerId;
  String get _storageKey => 'instructor_attendance_offline_queue_v1_user_$_ownerId';
  static const maxQueuedRecords = 100;
  static const maxSyncBatch = 50;

  final AttendanceQueueStorage _storage;
  Future<void> _writeLock = Future<void>.value();

  Future<List<AttendanceQueueEntry>> all() async {
    final raw = await _storage.read(_storageKey);
    if (raw == null || raw.isEmpty) return [];
    final decoded = jsonDecode(raw);
    if (decoded is! List) {
      throw const FormatException('Attendance queue data is invalid.');
    }
    return decoded
        .map((item) => AttendanceQueueEntry.fromJson(
              Map<String, dynamic>.from(item as Map),
            ))
        .toList(growable: true);
  }

  Future<AttendanceQueueEntry> enqueue({
    required int batchId,
    required int studentId,
    required String attendanceDate,
    required String status,
    required double latitude,
    required double longitude,
    required double accuracyMeters,
    required DateTime capturedAt,
    int maxLocationAgeMinutes = 10,
  }) async {
    if (batchId <= 0 || studentId <= 0) {
      throw ArgumentError('A valid batch and student are required.');
    }
    if (!const ['present', 'absent', 'late', 'excused'].contains(status)) {
      throw ArgumentError.value(status, 'status', 'Unsupported attendance status.');
    }
    if (latitude < -90 || latitude > 90 ||
        longitude < -180 || longitude > 180 ||
        !accuracyMeters.isFinite || accuracyMeters < 0) {
      throw ArgumentError('Valid foreground location metadata is required.');
    }
    if (maxLocationAgeMinutes < 1 || maxLocationAgeMinutes > 1440) {
      throw ArgumentError.value(maxLocationAgeMinutes, 'maxLocationAgeMinutes');
    }
    final timestamp = capturedAt.toUtc();
    final now = DateTime.now().toUtc();
    if (timestamp.isAfter(now.add(const Duration(minutes: 2))) ||
        timestamp.isBefore(now.subtract(Duration(minutes: maxLocationAgeMinutes)))) {
      throw ArgumentError('Location capture is stale or has an invalid timestamp.');
    }

    final entry = AttendanceQueueEntry(
      idempotencyKey: _uuidV4(),
      batchId: batchId,
      studentId: studentId,
      attendanceDate: attendanceDate,
      status: status,
      latitude: latitude,
      longitude: longitude,
      accuracyMeters: accuracyMeters,
      capturedAt: timestamp,
    );

    await _mutate((records) {
      if (records.length >= maxQueuedRecords) {
        throw StateError('Offline attendance queue is full. Sync before adding more.');
      }
      records.add(entry);
    });
    return entry;
  }

  Future<List<AttendanceQueueEntry>> pending() async =>
      (await all())
          .where((entry) => entry.syncState == AttendanceSyncState.pending)
          .toList(growable: false);

  /// Sends at most 50 records per request. The callback must use the current
  /// authenticated session; credentials are not included in the payload.
  ///
  /// Expected response:
  /// { "results": [
  ///   { "idempotency_key": "...", "status": "synced|duplicate|conflict|failed" }
  /// ] }
  Future<void> sync(
    Future<Map<String, dynamic>> Function(Map<String, dynamic> payload) send,
  ) async {
    final batch = (await all())
        .where((entry) => entry.syncState == AttendanceSyncState.pending ||
            entry.syncState == AttendanceSyncState.review)
        .take(maxSyncBatch)
        .toList(growable: false);
    if (batch.isEmpty) return;

    final response = await send({
      'records': batch.map((entry) => entry.toJson()).toList(growable: false),
    });
    final results = response['results'];
    if (results is! List) {
      throw const FormatException('Attendance sync response is missing results.');
    }

    final outcomes = <String, Map<String, dynamic>>{};
    for (final item in results) {
      if (item is Map) {
        final result = Map<String, dynamic>.from(item);
        final key = result['idempotency_key'];
        if (key is String) outcomes[key] = result;
      }
    }

    await _mutate((records) {
      for (var index = 0; index < records.length; index++) {
        final entry = records[index];
        final result = outcomes[entry.idempotencyKey];
        if (result == null) continue; // Keep unacknowledged records pending.
        switch (result['status']) {
          case 'synced':
          case 'duplicate':
            records[index] = entry.withSyncState(AttendanceSyncState.synced);
            break;
          case 'conflict':
            records[index] = entry.withSyncState(
              AttendanceSyncState.conflict,
              error: result['message']?.toString() ?? 'Server record conflicts.',
              serverAttendanceId: (result['attendance_id'] as num?)?.toInt(),
            );
            break;
          case 'needs_review':
            records[index] = entry.withSyncState(
              AttendanceSyncState.review,
              error: result['message']?.toString() ?? 'Administrator review is required.',
              serverReviewId: (result['review_id'] as num?)?.toInt(),
            );
            break;
          case 'failed':
            records[index] = entry.withSyncState(
              AttendanceSyncState.failed,
              error: result['message']?.toString() ?? 'Server rejected this record.',
            );
            break;
        }
      }
    });
  }

  Future<void> retryFailed() => _mutate((records) {
        for (var index = 0; index < records.length; index++) {
          if (records[index].syncState == AttendanceSyncState.failed) {
            records[index] = records[index].withSyncState(AttendanceSyncState.pending);
          }
        }
      });

  Future<void> markConflictResolved(String idempotencyKey) => _mutate((records) {
        for (var index = 0; index < records.length; index++) {
          if (records[index].idempotencyKey == idempotencyKey &&
              records[index].syncState == AttendanceSyncState.conflict) {
            records[index] = records[index].withSyncState(AttendanceSyncState.synced);
          }
        }
      });

  /// Remove only server-acknowledged records. Conflicts and failures are kept.
  Future<void> pruneSynced() => _mutate(
        (records) => records.removeWhere(
          (entry) => entry.syncState == AttendanceSyncState.synced,
        ),
      );

  Future<void> _mutate(void Function(List<AttendanceQueueEntry>) mutation) {
    final operation = _writeLock.catchError((_) {}).then((_) async {
      final records = await all();
      mutation(records);
      await _storage.write(
        _storageKey,
        jsonEncode(records.map((entry) => entry.toJson()).toList()),
      );
    });
    _writeLock = operation;
    return operation;
  }

  String _uuidV4() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final hex = bytes.map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-'
        '${hex.substring(20)}';
  }
}
