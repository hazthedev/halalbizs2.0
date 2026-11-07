import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';

/// `GET conversations` row: id, side, store{id,slug,name,logo}, buyer{name},
/// last_message{body,at,side}|null, unread_count, product|null.
class Conversation {
  Conversation(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;

  /// `buyer` or `seller` — which side the caller is on.
  String get side => asStr(json['side']) ?? 'buyer';
  Map<String, dynamic> get store => asMap(json['store']);
  String get storeName => asStr(store['name']) ?? '';
  String? get storeLogo => asStr(store['logo']);
  String? get buyerName => asStr(asMap(json['buyer'])['name']);

  /// The other party's name for the row title.
  String get title => side == 'seller' ? (buyerName ?? '') : storeName;
  Map<String, dynamic>? get lastMessage => json['last_message'] is Map ? asMap(json['last_message']) : null;
  String? get lastBody => asStr(lastMessage?['body']);
  DateTime? get lastAt => asDate(lastMessage?['at']);
  int get unreadCount => asInt(json['unread_count']) ?? 0;
  Map<String, dynamic>? get product => json['product'] is Map ? asMap(json['product']) : null;
}

/// Thread message: id, side, body, product|null, created_at.
class ChatMessage {
  ChatMessage(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get side => asStr(json['side']) ?? 'buyer';
  String get body => asStr(json['body']) ?? '';
  Map<String, dynamic>? get product => json['product'] is Map ? asMap(json['product']) : null;
  DateTime? get createdAt => asDate(json['created_at']);
}

class Thread {
  const Thread(this.messages, {required this.conversation});

  /// Oldest first (the API sends newest first; reversed here for the list).
  final List<ChatMessage> messages;
  final Conversation conversation;
}

/// Buyer↔seller chat (`auth:sanctum`); the same endpoints serve both sides.
class MessagesApi {
  MessagesApi(this._api);

  final ApiClient _api;

  Future<List<Conversation>> list() async => asMapList(await _api.get('conversations')).map(Conversation.new).toList();

  /// `POST conversations {store_id, product_id?}` → the (existing or new) thread summary.
  Future<Conversation> open({required int storeId, int? productId}) async =>
      Conversation(asMap(await _api.post('conversations', body: {'store_id': storeId, 'product_id': ?productId})));

  /// `GET conversations/{id}` — page 1 = latest 30, newest first; `meta.conversation` = summary.
  Future<Thread> thread(int id) async {
    Map<String, dynamic>? meta;
    final data = asMapList(await _api.get('conversations/$id', meta: (m) => meta = m));
    return Thread(data.map(ChatMessage.new).toList().reversed.toList(), conversation: Conversation(asMap(meta?['conversation'])));
  }

  Future<ChatMessage> send(int id, String body, {int? productId}) async =>
      ChatMessage(asMap(await _api.post('conversations/$id/messages', body: {'body': body, 'product_id': ?productId})));
}

final messagesApiProvider = Provider<MessagesApi>((ref) => MessagesApi(ref.watch(apiClientProvider)));

final conversationsProvider = FutureProvider<List<Conversation>>((ref) => ref.watch(messagesApiProvider).list());

/// Unread across every thread — the Account row badge.
final unreadMessagesProvider =
    Provider<int>((ref) => ref.watch(conversationsProvider).value?.fold<int>(0, (n, c) => n + c.unreadCount) ?? 0);
