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

  Future<void> login(String email, String password, String deviceName) async {
    final response = await client.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({'email': email, 'password': password, 'device_name': deviceName}),
    );
    final data = _decode(response);
    final token = data['access_token'];
    if (token is! String || token.isEmpty) {\n      throw const FormatException('Missing access token.');\n    }
    await storage.write(key: 'mobile_access_token', value: token);
  }

  Future<Map<String, dynamic>> get(String path) async {
    final token = await readToken();
    if (token == null) throw const ApiException('Please sign in again.');
    final response = await client.get(Uri.parse('$baseUrl$path'), headers: {
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    });
    return _decode(response);
  }

  Future<void> logout() async {
    final token = await readToken();
    if (token != null) {
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
    final dynamic data = jsonDecode(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final message = data is Map<String, dynamic> ? (data['message']?.toString() ?? 'Request failed.') : 'Request failed.';
      throw ApiException(message);
    }
    if (data is! Map<String, dynamic>) {\n      throw const FormatException('Unexpected API response.');\n    }
    return data;
  }
}

class ApiException implements Exception {
  final String message;
  const ApiException(this.message);
  @override
  String toString() => message;
}
