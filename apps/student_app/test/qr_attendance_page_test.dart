import 'package:flutter_test/flutter_test.dart';
import 'package:aloms_student_app/api_client.dart';
import 'package:aloms_student_app/qr_attendance_page.dart';

void main() {
  const base = 'https://alomsenglishcenter.com/api/v1';
  const session = '123e4567-e89b-12d3-a456-426614174000';

  test('accepts signed QR URL from the configured academy API', () {
    final path = validatedAttendanceQrCheckInPath(
      'https://alomsenglishcenter.com/api/v1/attendance/qr/$session/check-in'
      '?expires=1794912000&nonce=nonce-value&signature=signed-value',
      apiBaseUrl: base,
    );

    expect(path, startsWith('/attendance/qr/$session/check-in?'));
    expect(path, contains('signature=signed-value'));
  });

  test('rejects another host and non-HTTPS QR URLs', () {
    expect(
      () => validatedAttendanceQrCheckInPath(
        'https://evil.example/api/v1/attendance/qr/$session/check-in?expires=1&nonce=n&signature=s',
        apiBaseUrl: base,
      ),
      throwsA(isA<ApiException>()),
    );
    expect(
      () => validatedAttendanceQrCheckInPath(
        'http://alomsenglishcenter.com/api/v1/attendance/qr/$session/check-in?expires=1&nonce=n&signature=s',
        apiBaseUrl: base,
      ),
      throwsA(isA<ApiException>()),
    );
  });

  test('rejects malformed path, missing signature fields and fragments', () {
    final invalidUrls = [
      'https://alomsenglishcenter.com/api/v1/attendance/qr/$session/extra/check-in?expires=1&nonce=n&signature=s',
      'https://alomsenglishcenter.com/api/v1/attendance/qr/$session/check-in?expires=1&nonce=n',
      'https://alomsenglishcenter.com/api/v1/attendance/qr/$session/check-in?expires=1&nonce=n&signature=s#fragment',
    ];

    for (final url in invalidUrls) {
      expect(
        () => validatedAttendanceQrCheckInPath(url, apiBaseUrl: base),
        throwsA(isA<ApiException>()),
      );
    }
  });
}
