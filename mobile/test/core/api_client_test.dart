import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/auth/token_store.dart';

void main() {
  test('relative paths resolve under /api/v1/ (Dio does not insert the slash)', () {
    final dio = ApiClient(MemoryTokenStore()).dio;
    expect(dio.options.baseUrl, endsWith('/api/v1/'));
    expect(Uri.parse(dio.options.baseUrl).resolve('products').toString(), '$kApiBaseUrl/products');
  });
}
