import 'dart:convert';
import 'dart:math';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

enum LoginState { authenticated, verifyPhone, pendingApproval }

class LoginOutcome {
  const LoginOutcome(this.state, this.message, {this.challengeToken});

  final LoginState state;
  final String message;
  final String? challengeToken;
}

class MobileApi {
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://alomsenglishcenter.com/api/v1',
  );
  static const _tokenKey = 'mobile_access_token';
  static const _deviceIdKey = 'mobile_device_id';
  static const _challengeKey = 'mobile_login_challenge';

  final FlutterSecureStorage storage;
  final http.Client client;

  MobileApi({FlutterSecureStorage? storage, http.Client? client})
      : storage = storage ?? const FlutterSecureStorage(),
        client = client ?? http.Client();

  Future<String?> readToken() => storage.read(key: _tokenKey);

  Future<String> _deviceId() async {
    final saved = await storage.read(key: _deviceIdKey);
    if (saved != null && RegExp(r'^[a-f0-9]{64}$').hasMatch(saved)) {
      return saved;
    }

    final random = Random.secure();
    final bytes = List<int>.generate(32, (_) => random.nextInt(256));
    final value = bytes
        .map((byte) => byte.toRadixString(16).padLeft(2, '0'))
        .join();
    await storage.write(key: _deviceIdKey, value: value);
    return value;
  }

  Future<bool> restoreSession() async {
    final token = await readToken();
    if (token == null) return false;
    try {
      await get('/auth/me');
      return true;
    } on ApiException catch (exception) {
      if (exception.statusCode == 401) {
        await storage.delete(key: _tokenKey);
      }
      return false;
    }
  }

  Future<LoginOutcome> login(
    String email,
    String password,
    String deviceName,
  ) async {
    final response = await client.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: _jsonHeaders,
      body: jsonEncode({
        'email': email,
        'password': password,
        'device_name': deviceName,
        'device_id': await _deviceId(),
      }),
    );
    final data = _decode(response);
    final token = data['access_token'];
    if (token is String && token.isNotEmpty) {
      await _saveToken(token);
      return LoginOutcome(
        LoginState.authenticated,
        'Signed in successfully.',
      );
    }

    final challenge = data['challenge_token'];
    if (data['status'] == 'phone_verification_required' &&
        challenge is String &&
        challenge.isNotEmpty) {
      await storage.write(key: _challengeKey, value: challenge);
      return LoginOutcome(
        LoginState.verifyPhone,
        data['message']?.toString() ??
            'Enter the verification code sent to your registered mobile number.',
        challengeToken: challenge,
      );
    }

    throw const FormatException('Unexpected login response.');
  }

  Future<LoginOutcome> verifyDevice(String code) async {
    final challenge = await storage.read(key: _challengeKey);
    if (challenge == null) {
      throw const ApiException('The verification request expired. Sign in again.');
    }

    final response = await client.post(
      Uri.parse('$baseUrl/auth/verify-device'),
      headers: _jsonHeaders,
      body: jsonEncode({'challenge_token': challenge, 'code': code.trim()}),
    );
    final data = _decode(response);
    final token = data['access_token'];
    if (token is String && token.isNotEmpty) {
      await _saveToken(token);
      await storage.delete(key: _challengeKey);
      return LoginOutcome(
        LoginState.authenticated,
        'Your device has been verified.',
      );
    }

    if (data['status'] == 'pending_admin_approval') {
      return LoginOutcome(
        LoginState.pendingApproval,
        data['message']?.toString() ??
            'Your device is waiting for administrator approval.',
        challengeToken: challenge,
      );
    }

    throw const FormatException('Unexpected device verification response.');
  }

  Future<LoginOutcome> checkDeviceStatus() async {
    final challenge = await storage.read(key: _challengeKey);
    if (challenge == null) {
      throw const ApiException('The approval request expired. Sign in again.');
    }

    final response = await client.post(
      Uri.parse('$baseUrl/auth/device-status'),
      headers: _jsonHeaders,
      body: jsonEncode({'challenge_token': challenge}),
    );
    final data = _decode(response);
    final token = data['access_token'];
    if (token is String && token.isNotEmpty) {
      await _saveToken(token);
      await storage.delete(key: _challengeKey);
      return LoginOutcome(
        LoginState.authenticated,
        'Your device has been approved.',
      );
    }

    if (data['status'] == 'pending_admin_approval') {
      return LoginOutcome(
        LoginState.pendingApproval,
        data['message']?.toString() ??
            'Your device is waiting for administrator approval.',
        challengeToken: challenge,
      );
    }

    throw const FormatException('Unexpected device approval response.');
  }

  Future<void> clearLoginChallenge() =>
      storage.delete(key: _challengeKey);

  Future<void> _saveToken(String token) async {
    await storage.write(key: _tokenKey, value: token);
  }

  Future<Map<String, dynamic>> get(String path) async {
    final token = await readToken();
    if (token == null) {
      throw const ApiException('Please sign in again.', 401);
    }
    final response = await client.get(
      Uri.parse('$baseUrl$path'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );
    if (response.statusCode == 401) {
      await storage.delete(key: _tokenKey);
    }
    return _decode(response);
  }

  Future<Map<String, dynamic>> post(String path) async {
    final token = await readToken();
    if (token == null) {
      throw const ApiException('Please sign in again.', 401);
    }
    final response = await client.post(
      Uri.parse('$baseUrl$path'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );
    if (response.statusCode == 401) {
      await storage.delete(key: _tokenKey);
    }
    return _decode(response);
  }

  Future<void> logout() async {
    final token = await readToken();
    if (token != null) {
      try {
        await client.post(
          Uri.parse('$baseUrl/auth/logout'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $token',
          },
        );
      } finally {
        await storage.delete(key: _tokenKey);
      }
    }
  }

  Map<String, String> get _jsonHeaders => const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      };

  Map<String, dynamic> _decode(http.Response response) {
    dynamic data;
    try {
      data = jsonDecode(response.body);
    } on FormatException {
      throw ApiException(
        'The server returned an unreadable response.',
        response.statusCode,
      );
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final message = data is Map<String, dynamic>
          ? (data['message']?.toString() ?? 'Request failed.')
          : 'Request failed.';
      throw ApiException(message, response.statusCode);
    }
    if (data is! Map<String, dynamic>) {
      throw const FormatException('Unexpected API response.');
    }
    return data;
  }
}

class ApiException implements Exception {
  final String message;
  final int? statusCode;

  const ApiException(this.message, [this.statusCode]);

  @override
  String toString() => message;
}
