import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class MobileApi {
  static const baseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'https://alomsenglishcenter.com/api/v1');
  final FlutterSecureStorage storage;
  final http.Client client;
  MobileApi({FlutterSecureStorage? storage, http.Client? client})
      : storage = storage ?? const FlutterSecureStorage(),
        client = client ?? http.Client();

  Future<String?> readToken() => storage.read(key: 'mobile_access_token');

  /// Restores a session only after the server confirms that its token is valid.
  /// This also detects tokens revoked by an administrator while the app was closed.
  Future<bool> restoreSession() async {
    final token = await readToken();
    if (token == null || token.isEmpty) return false;
    try {
      await get('/auth/me');
      return true;
    } on ApiException catch (exception) {
      if (exception.statusCode == 401 || exception.statusCode == 403) {
        await storage.delete(key: 'mobile_access_token');
      }
      return false;
    } on FormatException {
      return false;
    }
  }

  Future<void> login(String email, String password, String deviceName) async {
    final response = await client.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({'email': email, 'password': password, 'device_name': deviceName}),
    );
    final data = _decode(response);
    final token = data['access_token'];
    if (token is! String || token.isEmpty) {
      throw const FormatException('Missing access token.');
    }
    await storage.write(key: 'mobile_access_token', value: token);
  }

  Future<Map<String, dynamic>> get(String path) async {
    final token = await readToken();
    if (token == null || token.isEmpty) {
      throw const ApiException('Please sign in again.', 401);
    }
    final response = await client.get(Uri.parse('$baseUrl$path'), headers: {
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    });
    if (response.statusCode == 401 || response.statusCode == 403) {
      await storage.delete(key: 'mobile_access_token');
    }
    return _decode(response);
  }

  Future<void> logout() async {
    final token = await readToken();
    if (token != null && token.isNotEmpty) {
      try {
        await client.post(Uri.parse('$baseUrl/auth/logout'), headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        });
      } finally {
        await storage.delete(key: 'mobile_access_token');
      }
    }
  }

  Map<String, dynamic> _decode(http.Response response) {
    dynamic data;
    try {
      data = jsonDecode(response.body);
    } on FormatException {
      throw ApiException('The server returned an unreadable response.', response.statusCode);
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
