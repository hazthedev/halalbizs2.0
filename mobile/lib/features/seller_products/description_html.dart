import 'package:html/parser.dart' as html_parser;

/// Product descriptions are stored as rich-text HTML (the web seller centre's
/// editor). The app edits only the simple shape — paragraphs and line breaks —
/// as plain text, and leaves anything richer to the web so no formatting is lost.

final _allowedTag = RegExp(r'</?p>|<br\s*/?>', caseSensitive: false);
final _anyTag = RegExp(r'<[a-zA-Z/!]');

/// True when [html] holds nothing but bare `<p>` / `<br>` tags (or no tags).
bool isPlainParagraphHtml(String html) => !_anyTag.hasMatch(html.replaceAll(_allowedTag, ''));

/// `<p>a</p><p>b<br>c</p>` → "a\n\nb\nc", entities decoded. Only for
/// [isPlainParagraphHtml] input.
String htmlToParagraphs(String html) {
  final text = html
      .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
      .replaceAll(RegExp(r'</p>\s*<p>', caseSensitive: false), '\n\n')
      .replaceAll(RegExp(r'</?p>', caseSensitive: false), '');
  return (html_parser.parseFragment(text).text ?? '').trim();
}

/// Inverse of [htmlToParagraphs]: one `<p>` per blank-line-separated
/// paragraph, single newlines as `<br>`, `& < >` escaped. '' for blank input.
String paragraphsToHtml(String plain) => plain
    .trim()
    .split(RegExp(r'\n\s*\n'))
    .map((p) => p.trim())
    .where((p) => p.isNotEmpty)
    .map(
      (p) => '<p>${p.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll(RegExp(r'\s*\n\s*'), '<br>')}</p>',
    )
    .join();
