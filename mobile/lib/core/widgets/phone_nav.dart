import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../theme/souk_theme.dart';

/// One bottom tab = one `StatefulShellBranch` (ASTRA phone model). Tab tap →
/// `goBranch(i)` restores that tab's stack; re-tap → back to the tab root.
class SoukTab {
  const SoukTab({required this.label, required this.icon, required this.root});

  final String label;
  final FaIconData icon;
  final String root;
}

const buyerTabs = [
  SoukTab(label: 'Home', icon: FontAwesomeIcons.house, root: '/home'),
  SoukTab(label: 'Search', icon: FontAwesomeIcons.magnifyingGlass, root: '/search'),
  SoukTab(label: 'Cart', icon: FontAwesomeIcons.cartShopping, root: '/cart'),
  SoukTab(label: 'Orders', icon: FontAwesomeIcons.boxOpen, root: '/orders'),
  SoukTab(label: 'Account', icon: FontAwesomeIcons.circleUser, root: '/account'),
];

const sellerTabs = [
  SoukTab(label: 'Dashboard', icon: FontAwesomeIcons.gaugeHigh, root: '/seller'),
  SoukTab(label: 'Orders', icon: FontAwesomeIcons.boxOpen, root: '/seller/orders'),
  SoukTab(label: 'Products', icon: FontAwesomeIcons.tags, root: '/seller/products'),
  SoukTab(label: 'Earnings', icon: FontAwesomeIcons.wallet, root: '/seller/earnings'),
  SoukTab(label: 'Store', icon: FontAwesomeIcons.store, root: '/seller/store'),
];

/// Shell around a `StatefulNavigationShell`: the branch body plus the tab bar.
class SoukTabShell extends StatelessWidget {
  const SoukTabShell({super.key, required this.shell, required this.tabs, this.labels, this.badges = const {}});

  final StatefulNavigationShell shell;
  final List<SoukTab> tabs;

  /// Translated labels in [tabs] order (defaults to [SoukTab.label]).
  final List<String>? labels;

  /// Tab index → count pill on the icon (cart items, unread). 0 hides it.
  final Map<int, int> badges;

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: SoukColors.paper,
        body: shell,
        bottomNavigationBar: SoukTabBar(
          tabs: tabs,
          labels: labels,
          badges: badges,
          currentIndex: shell.currentIndex,
          onSelect: (i) => shell.goBranch(i, initialLocation: i == shell.currentIndex),
        ),
      );
}

/// CupertinoTabBar on iOS, Material 3 NavigationBar elsewhere. Labels never
/// scale with Dynamic Type (five labels must fit one line each).
class SoukTabBar extends StatelessWidget {
  const SoukTabBar({
    super.key,
    required this.tabs,
    required this.currentIndex,
    required this.onSelect,
    this.labels,
    this.badges = const {},
  });

  final List<SoukTab> tabs;
  final List<String>? labels;
  final Map<int, int> badges;
  final int currentIndex;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    final index = currentIndex.clamp(0, tabs.length - 1);
    String label(int i) => labels != null && i < labels!.length ? labels![i] : tabs[i].label;
    Widget icon(int i, double size, Color color) {
      final count = badges[i] ?? 0;
      final base = FaIcon(tabs[i].icon, size: size, color: color);
      if (count <= 0) return base;
      return Badge.count(
        key: Key('tab-badge-$i'),
        count: count,
        backgroundColor: SoukColors.emerald,
        textColor: Colors.white,
        child: base,
      );
    }
    return MediaQuery.withNoTextScaling(
      child: Builder(builder: (context) {
        if (Theme.of(context).platform == TargetPlatform.iOS) {
          return CupertinoTabBar(
            key: const ValueKey('souk-tab-bar'),
            currentIndex: index,
            onTap: onSelect,
            height: 50,
            iconSize: 22,
            activeColor: SoukColors.emerald,
            inactiveColor: SoukColors.inkFaint,
            backgroundColor: SoukColors.surface.withValues(alpha: .96),
            border: const Border(top: BorderSide(color: SoukColors.line)),
            items: [
              for (var i = 0; i < tabs.length; i++)
                BottomNavigationBarItem(
                  icon: Padding(
                    padding: const EdgeInsets.only(bottom: 2),
                    child: icon(i, 20, i == index ? SoukColors.emerald : SoukColors.inkFaint),
                  ),
                  label: label(i),
                ),
            ],
          );
        }
        return NavigationBar(
          key: const ValueKey('souk-tab-bar'),
          selectedIndex: index,
          onDestinationSelected: onSelect,
          labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
          destinations: [
            for (var i = 0; i < tabs.length; i++)
              NavigationDestination(
                icon: icon(i, 19, SoukColors.inkFaint),
                selectedIcon: icon(i, 19, SoukColors.emerald),
                label: label(i),
              ),
          ],
        );
      }),
    );
  }
}
