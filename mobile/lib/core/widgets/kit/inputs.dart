import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../../theme/souk_theme.dart';

/// Label above, control, then help / error text — the web form-field anatomy.
class SoukField extends StatelessWidget {
  const SoukField({super.key, required this.label, required this.child, this.error, this.help, this.required = false});

  final String label;
  final Widget child;
  final String? error;
  final String? help;
  final bool required;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text.rich(TextSpan(text: label, style: SoukText.label, children: [
            if (required) const TextSpan(text: ' *', style: TextStyle(color: SoukColors.danger)),
          ])),
          const SizedBox(height: 6),
          child,
          if (error != null && error!.isNotEmpty) ...[
            const SizedBox(height: SoukSpace.s1),
            Text(error!, style: SoukText.error),
          ] else if (help != null) ...[
            const SizedBox(height: SoukSpace.s1),
            Text(help!, style: SoukText.meta),
          ],
        ],
      );
}

/// Text input with the kit's label/error anatomy. [error] comes from the
/// form validator or `fieldError(e, 'path')` for 422s.
class SoukTextField extends StatelessWidget {
  const SoukTextField({
    super.key,
    required this.label,
    this.controller,
    this.hint,
    this.required = false,
    this.error,
    this.help,
    this.onChanged,
    this.onSubmitted,
    this.keyboardType,
    this.textInputAction,
    this.autofillHints,
    this.maxLines = 1,
    this.enabled = true,
    this.obscureText = false,
    this.prefixIcon,
    this.suffix,
    this.autofocus = false,
    this.fieldKey,
    this.validator,
    this.autocorrect = true,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final bool required;
  final String? error;
  final String? help;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final Iterable<String>? autofillHints;
  final int maxLines;
  final bool enabled;
  final bool obscureText;
  final FaIconData? prefixIcon;
  final Widget? suffix;
  final bool autofocus;

  /// Key for the inner TextFormField (tests: `enterText(find.byKey(...))`).
  final Key? fieldKey;
  final FormFieldValidator<String>? validator;
  final bool autocorrect;

  @override
  Widget build(BuildContext context) => SoukField(
        label: label,
        required: required,
        error: error,
        help: help,
        child: TextFormField(
          key: fieldKey,
          controller: controller,
          onChanged: onChanged,
          onFieldSubmitted: onSubmitted,
          keyboardType: keyboardType,
          textInputAction: textInputAction ?? (maxLines > 1 ? null : TextInputAction.next),
          autofillHints: autofillHints,
          maxLines: obscureText ? 1 : maxLines,
          minLines: maxLines > 1 ? 3 : null,
          enabled: enabled,
          obscureText: obscureText,
          autofocus: autofocus,
          autocorrect: autocorrect,
          validator: validator,
          style: SoukText.body,
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: prefixIcon == null
                ? null
                : Padding(
                    padding: const EdgeInsets.only(left: SoukSpace.s3, right: SoukSpace.s2),
                    child: Center(widthFactor: 1, heightFactor: 1, child: FaIcon(prefixIcon, size: 14, color: SoukColors.inkFaint)),
                  ),
            suffixIcon: suffix,
            // Error text is rendered by SoukField; keep the border red only.
            errorStyle: const TextStyle(height: 0, fontSize: 0),
            errorText: error != null && error!.isNotEmpty ? '' : null,
          ),
        ),
      );
}

/// Password input with a show/hide toggle.
class SoukPasswordField extends StatefulWidget {
  const SoukPasswordField({
    super.key,
    required this.label,
    this.controller,
    this.error,
    this.help,
    this.required = false,
    this.onSubmitted,
    this.autofillHints = const [AutofillHints.password],
    this.textInputAction,
    this.enabled = true,
    this.fieldKey,
    this.validator,
  });

  final String label;
  final TextEditingController? controller;
  final String? error;
  final String? help;
  final bool required;
  final ValueChanged<String>? onSubmitted;
  final Iterable<String>? autofillHints;
  final TextInputAction? textInputAction;
  final bool enabled;
  final Key? fieldKey;
  final FormFieldValidator<String>? validator;

  @override
  State<SoukPasswordField> createState() => _SoukPasswordFieldState();
}

class _SoukPasswordFieldState extends State<SoukPasswordField> {
  bool _obscure = true;

  @override
  Widget build(BuildContext context) => SoukTextField(
        label: widget.label,
        controller: widget.controller,
        error: widget.error,
        help: widget.help,
        required: widget.required,
        onSubmitted: widget.onSubmitted,
        autofillHints: widget.autofillHints,
        textInputAction: widget.textInputAction,
        enabled: widget.enabled,
        obscureText: _obscure,
        prefixIcon: FontAwesomeIcons.lock,
        fieldKey: widget.fieldKey,
        validator: widget.validator,
        autocorrect: false,
        suffix: IconButton(
          tooltip: _obscure ? 'Show password' : 'Hide password',
          onPressed: () => setState(() => _obscure = !_obscure),
          icon: FaIcon(_obscure ? FontAwesomeIcons.eyeSlash : FontAwesomeIcons.eye, size: 15, color: SoukColors.inkFaint),
        ),
      );
}

/// Dropdown with the kit anatomy. [options] value → display text, in order.
class SoukDropdown<T> extends StatelessWidget {
  const SoukDropdown({
    super.key,
    required this.label,
    required this.options,
    required this.value,
    required this.onChanged,
    this.hint = 'Any',
    this.error,
    this.required = false,
    this.enabled = true,
    this.allowNull = true,
  });

  final String label;
  final Map<T, String> options;
  final T? value;
  final ValueChanged<T?> onChanged;
  final String hint;
  final String? error;
  final bool required;
  final bool enabled;

  /// Adds a leading [hint] entry that selects null.
  final bool allowNull;

  @override
  Widget build(BuildContext context) => SoukField(
        label: label,
        error: error,
        required: required,
        child: DropdownButtonFormField<T?>(
          key: ValueKey(value),
          initialValue: value,
          isExpanded: true,
          borderRadius: BorderRadius.circular(SoukRadius.control),
          dropdownColor: SoukColors.surface,
          style: SoukText.body,
          icon: const Icon(Icons.expand_more_rounded, color: SoukColors.inkFaint),
          hint: Text(hint, style: SoukText.body.copyWith(color: SoukColors.inkFaint)),
          items: [
            if (allowNull) DropdownMenuItem<T?>(value: null, child: Text(hint, style: const TextStyle(color: SoukColors.inkFaint))),
            for (final e in options.entries) DropdownMenuItem<T?>(value: e.key, child: Text(e.value)),
          ],
          onChanged: enabled ? onChanged : null,
        ),
      );
}

/// Selectable pill chip (variant picker, category filter, sort).
class SoukChip extends StatelessWidget {
  const SoukChip({super.key, required this.label, required this.selected, this.onTap, this.enabled = true});

  final String label;
  final bool selected;
  final VoidCallback? onTap;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final fg = !enabled ? SoukColors.inkFaint : (selected ? SoukColors.emerald : SoukColors.ink);
    return Semantics(
      button: true,
      selected: selected,
      enabled: enabled,
      label: label,
      child: Material(
        color: selected ? SoukColors.emeraldTint : SoukColors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SoukRadius.pill),
          side: BorderSide(color: selected ? SoukColors.emerald : SoukColors.lineStrong, width: selected ? 1.5 : 1),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(SoukRadius.pill),
          onTap: enabled ? onTap : null,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 40),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4),
              child: Center(
                widthFactor: 1,
                child: Text(label,
                    style: SoukText.small.copyWith(
                      color: fg,
                      fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                      decoration: enabled ? null : TextDecoration.lineThrough,
                    )),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
