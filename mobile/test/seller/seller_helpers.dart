import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';

/// Payloads copied from the seller API contract (PR #176).
Map<String, dynamic> fakeSellerOrder({
  int id = 5,
  String status = 'confirmed',
  Map<String, dynamic>? returnRequest,
  bool detail = true,
}) =>
    {
      'id': id,
      'sub_order_no': 'SO2611UGYIBY',
      'order_no': 'MP2611V7PVXY',
      'status': status,
      'status_label': status,
      'placed_at': '2026-11-07T07:52:17+00:00',
      'buyer': {'name': 'Lim Mei Ling', 'phone': '+60123456789'},
      'address': {
        'recipient_name': 'Lim Mei Ling',
        'phone': '+60123456789',
        'line1': '1 Jalan Demo',
        'line2': null,
        'postcode': '47500',
        'city': 'Subang Jaya',
        'state': 'Selangor',
        'country': 'MY',
      },
      'items': [
        {
          'id': 1,
          'product_id': 7,
          'name': 'Madu Tualang Asli',
          'variant_name': '500g',
          'qty': 2,
          'unit_price_sen': 1250,
          'line_total_sen': 2500,
          'product_image': null,
        },
      ],
      'subtotal_sen': 2500,
      'shipping_sen': 500,
      'discount_sen': 0,
      'tax_sen': 0,
      'total_sen': 3000,
      'commission_rate': '5.00',
      'commission_sen': 125,
      'payment': {'method': 'cod', 'status': 'pending'},
      'tracking': {'courier': null, 'no': null, 'url': null, 'status': null},
      'return_request': returnRequest,
      if (detail)
        'history': [
          {'status': 'confirmed', 'from': 'pending_payment', 'at': '2026-11-07T07:52:17+00:00', 'actor': 'system', 'note': null},
        ],
      'shipped_at': null,
      'delivered_at': null,
      'completed_at': null,
      'cancelled_at': null,
    };

Map<String, dynamic> fakeSellerProduct({int id = 7, String status = 'draft', int variants = 1}) => {
      'id': id,
      'slug': 'madu-tualang',
      'name': 'Madu Tualang Asli',
      'name_translations': {'en': 'Madu Tualang Asli', 'ms': 'Madu Tualang Asli', 'vi': ''},
      'status': status,
      'status_label': status,
      'category': {'id': 2, 'name': 'Rice & Grains'},
      'thumb': null,
      'price_sen': 1250,
      'sale_price_sen': null,
      'min_price_sen': 1250,
      'stock_total': 12,
      'variants_count': variants,
      'cod_enabled': true,
      'updated_at': '2026-11-07T07:52:17+00:00',
      'description': 'Raw wild honey.',
      'description_translations': {'en': 'Raw wild honey.', 'ms': '', 'vi': ''},
      'condition': 'new',
      'variants': [
        for (var i = 0; i < variants; i++)
          {
            'id': 20 + i,
            'options_label': variants > 1 ? 'Size ${i + 1}' : null,
            'sku': 'SKU-$i',
            'price_sen': 1250,
            'sale_price_sen': null,
            'stock': 12,
            'is_default': i == 0,
            'image': null,
          },
      ],
      'images': [
        {'id': 14, 'url': 'http://x/14/card.jpg', 'thumb': 'http://x/14/thumb.jpg'},
      ],
      'weight_grams': 500,
      'length_mm': null,
      'width_mm': null,
      'height_mm': null,
      'sale_starts_at': null,
      'sale_ends_at': null,
      'published_at': null,
      'blocked_reason': null,
    };

Map<String, dynamic> fakeDashboard() => {
      'today_sales_sen': 123450,
      'today_orders': 11,
      'orders_by_status': {'confirmed': 2, 'processing': 0, 'completed': 9},
      'to_ship': 2,
      'live_products': 10,
      'low_stock': 3,
      'available_sen': 50000,
      'held_sen': 2500,
      'recent_orders': [fakeSellerOrder(detail: false)],
    };

Map<String, dynamic> fakeEarnings({int availableSen = 50000, int minSen = 5000}) => {
      'available_sen': availableSen,
      'held_sen': 2500,
      'paid_out_sen': 10000,
      'payout_min_sen': minSen,
      'open_payout': null,
      'ledger': [
        {'id': 1, 'type': 'sale', 'type_label': 'Sale', 'amount_sen': 2375, 'reference': 'SO2611UGYIBY', 'sub_order_id': 5, 'created_at': '2026-11-07T07:52:17+00:00'},
        {'id': 2, 'type': 'commission', 'type_label': 'Commission', 'amount_sen': -125, 'reference': 'SO2611UGYIBY', 'sub_order_id': 5, 'created_at': '2026-11-07T07:52:17+00:00'},
      ],
      'payouts': [
        {'id': 1, 'payout_no': 'PO2611AAAA', 'amount_sen': 10000, 'status': 'paid', 'status_label': 'Paid', 'requested_at': '2026-11-01T00:00:00+00:00', 'paid_at': '2026-11-02T00:00:00+00:00'},
      ],
    };

Map<String, dynamic> fakeStore() => {
      'id': 1,
      'slug': 'goodwin-ltd',
      'name': 'Goodwin Ltd',
      'description': 'Honey and more.',
      'state': 'Pahang',
      'status': 'approved',
      'status_label': 'Approved',
      'holiday_mode': false,
      'logo': null,
      'banner': null,
      'shipping': {'mode': 'flat', 'flat_fee_sen': 500, 'free_above_sen': null, 'matrix': null},
      'bank': {'bank_name': 'Bank Islam', 'account_name': 'Goodwin Ltd', 'account_no': '******8705'},
      'created_at': '2026-11-07T07:52:10+00:00',
    };

/// The buyer-shell reads the harness triggers at `/home` plus the seller profile.
Map<String, FakeHandler> sellerBaseRoutes() => {
      'GET me': (_, _) => fakeUser(storeStatus: 'approved'),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [
            {'id': 1, 'slug': 'groceries', 'name': 'Groceries', 'parent_id': null},
            {'id': 2, 'slug': 'rice-grains', 'name': 'Rice & Grains', 'parent_id': 1},
          ],
      'GET seller/couriers': (_, _) => ['J&T Express', 'Pos Laju'],
    };

ApiException validation(Map<String, List<String>> fields) =>
    ApiException('validation_failed', 'The given data was invalid.', status: 422, fields: fields);
