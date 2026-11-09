import 'package:flutter_test/flutter_test.dart';
import 'package:aloms_instructor_app/api_client.dart';
void main() {
  test('uses the production API base URL by default', () {
    expect(MobileApi.baseUrl, 'https://alomsenglishcenter.com/api/v1');
  });
}
