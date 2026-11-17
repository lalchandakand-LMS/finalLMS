import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:aloms_instructor_app/attendance_offline_queue.dart';

class MemoryQueueStorage implements AttendanceQueueStorage {
  final Map<String, String> values = {};

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }

  @override
  Future<void> delete(String key) async {
    values.remove(key);
  }
}

void main() {
  late MemoryQueueStorage storage;
  late AttendanceOfflineQueue queue;

  setUp(() {
    storage = MemoryQueueStorage();
    queue = AttendanceOfflineQueue(storage: storage);
  });

  Future<dynamic> addRecord({String status = 'present'}) => queue.enqueue(
        batchId: 12,
        studentId: 34,
        attendanceDate: DateTime.now().toIso8601String().substring(0, 10),
        status: status,
        latitude: 23.8103,
        longitude: 90.4125,
        accuracyMeters: 12.5,
        capturedAt: DateTime.now().toUtc(),
      );

  test('persists a UUID-keyed record with foreground location metadata', () async {
    final entry = await addRecord();
    final records = await queue.pending();

    expect(records, hasLength(1));
    expect(records.single.idempotencyKey, matches(RegExp(
      r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
    )));
    expect(records.single.idempotencyKey, entry.idempotencyKey);
    expect(records.single.latitude, 23.8103);
    expect(records.single.longitude, 90.4125);

    final persisted = jsonDecode(storage.values.values.single) as List;
    expect(persisted.single, isNot(contains('access_token')));
    expect(persisted.single, isNot(contains('bearer_token')));
  });

  test('rejects missing or invalid location and unsupported status', () async {
    expectLater(
      queue.enqueue(
        batchId: 1,
        studentId: 2,
        attendanceDate: '2026-11-17',
        status: 'present',
        latitude: 91,
        longitude: 0,
        accuracyMeters: 5,
        capturedAt: DateTime.now(),
      ),
      throwsArgumentError,
    );
    expectLater(
      addRecord(status: 'unknown'),
      throwsArgumentError,
    );
    final excused = await addRecord(status: 'excused');
    expect(excused.status, 'excused');
    expect(await queue.pending(), hasLength(1));
  });

  test('uses the institution maximum location age for offline capture', () async {
    expectLater(
      queue.enqueue(
        batchId: 12,
        studentId: 34,
        attendanceDate: '2026-11-17',
        status: 'present',
        latitude: 23.8103,
        longitude: 90.4125,
        accuracyMeters: 12.5,
        capturedAt: DateTime.now().subtract(const Duration(minutes: 2)),
        maxLocationAgeMinutes: 1,
      ),
      throwsArgumentError,
    );
    expect(await queue.pending(), isEmpty);
  });

  test('marks duplicate as synced and keeps conflict and failed records', () async {
    final synced = await addRecord();
    final conflict = await addRecord();
    final failed = await addRecord();

    await queue.sync((payload) async {
      final records = payload['records'] as List;
      expect(records, hasLength(3));
      return {
        'results': [
          {'idempotency_key': synced.idempotencyKey, 'status': 'duplicate'},
          {
            'idempotency_key': conflict.idempotencyKey,
            'status': 'conflict',
            'attendance_id': 4321,
            'message': 'A different attendance record exists.',
          },
          {
            'idempotency_key': failed.idempotencyKey,
            'status': 'failed',
            'message': 'Enrollment is no longer active.',
          },
        ],
      };
    });

    final all = await queue.all();
    expect(all[0].syncState, AttendanceSyncState.synced);
    expect(all[1].syncState, AttendanceSyncState.conflict);
    expect(all[1].lastError, contains('different attendance'));
    expect(all[1].serverAttendanceId, 4321);
    expect(all[2].syncState, AttendanceSyncState.failed);
    expect((await queue.pending()), isEmpty);
  });

  test('keeps stale records in review state until the server approves them', () async {
    final entry = await addRecord();
    await queue.sync((_) async => {
          'results': [
            {
              'idempotency_key': entry.idempotencyKey,
              'status': 'needs_review',
              'review_id': 55,
            },
          ],
        });
    var saved = (await queue.all()).single;
    expect(saved.syncState, AttendanceSyncState.review);
    expect(saved.serverReviewId, 55);

    await queue.sync((_) async => {
          'results': [
            {'idempotency_key': entry.idempotencyKey, 'status': 'duplicate'},
          ],
        });
    saved = (await queue.all()).single;
    expect(saved.syncState, AttendanceSyncState.synced);
  });

  test('marks a conflict resolved only after an explicit audited server action', () async {
    final entry = await addRecord();
    await queue.sync((_) async => {
          'results': [
            {
              'idempotency_key': entry.idempotencyKey,
              'status': 'conflict',
              'attendance_id': 987,
            },
          ],
        });

    await queue.markConflictResolved(entry.idempotencyKey);
    expect((await queue.all()).single.syncState, AttendanceSyncState.synced);
  });

  test('unacknowledged records remain pending and failed records can retry', () async {
    final entry = await addRecord();
    await queue.sync((_) async => {'results': []});
    expect((await queue.pending()).single.idempotencyKey, entry.idempotencyKey);

    await queue.sync((payload) async {
      final record = (payload['records'] as List).single as Map;
      return {
        'results': [
          {'idempotency_key': record['idempotency_key'], 'status': 'failed'},
        ],
      };
    });
    await queue.retryFailed();
    expect((await queue.pending()).single.idempotencyKey, entry.idempotencyKey);
  });

  test('prunes only server-acknowledged records', () async {
    final synced = await addRecord();
    final conflict = await addRecord();
    await queue.sync((_) async => {
          'results': [
            {'idempotency_key': synced.idempotencyKey, 'status': 'synced'},
            {'idempotency_key': conflict.idempotencyKey, 'status': 'conflict'},
          ],
        });

    await queue.pruneSynced();
    final remaining = await queue.all();
    expect(remaining, hasLength(1));
    expect(remaining.single.syncState, AttendanceSyncState.conflict);
  });
}
