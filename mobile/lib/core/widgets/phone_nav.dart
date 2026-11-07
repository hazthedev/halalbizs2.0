import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../theme/souk_theme.dart';

/// One bottom tab = one `StatefulShellBranch` (ASTRA phone model). Tab tap →
/// `goBranch(i)` restores that tab's stack; re-tap → back to the tab root.
class SoukTab {
  const SoukTab({required this.icon, required this.root});

  final FaIconData icon;
  final String root;
}

const buyerTabs = [
  SoukTab(icon: FontAwesomeIcons.house, root: '/home'),
  SoukTab(icon: FontAwesomeIcons.magnifyingGlass, root: '/search'),
  SoukTab(icon: FontAwesomeIcons.cartShopping, root: '/cart'),
  SoukTab(icon: FontAwesomeIcons.boxOpen, root: '/orders'),
  SoukTab(icon: FontAwesomeIcons.circleUser, root: '/account'),
];

const sellerTabs = [
  SoukTab(icon: FontAwesomeIcons.gaugeHigh, root: '/seller'),
  SoukTab(icon: FontAwesomeIcons.boxOpen, root: '/seller/orders'),
  SoukTab(icon: FontAwesomeIcons.tags, root: '/seller/products'),
  SoukTab(icon: FontAwesomeIcons.wallet, root: '/seller/earnings'),
  SoukTab(icon: FontAwesomeIcons.store, root: '/seller/store'),
];

/// Shell around a `StatefulNavigationShell`: the branch body plus the tab bar.
class SoukTabShell extends StatelessWidget {
  const SoukTabShell({super.key, required this.shell, required this.tabs, required this.labels});

  final StatefulNavigationShell shell;
  final List<SoukTab> tabs;

  /// Translated labels in [tabs] order.
  final List<String> labels;

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: SoukColors.paper,
        body: shell,
        bottomNavigationBar: SoukTabBar(
          tabs: tabs,
          labels: labels,
          currentIndex: shell.currentIndex,
          onSelect: (i) => shell.goBranch(i, initialLocation: i == shell.currentIndex),
        ),
      );
}

/// CupertinoTabBar on iOS, Material 3 NavigationBar elsewhere. Labels scale
/// with Dynamic Type up to 1.2× (five labels must fit one line each).
class SoukTabBar extends StatelessWidget {
  const SoukTabBar({super.key, required this.tabs, required this.currentIndex, required this.onSelect, required this.labels});

  final List<SoukTab> tabs;
  final List<String> labels;
  final int currentIndex;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    final index = currentIndex.clamp(0, tabs.length - 1);
    String label(int i) => i < labels.length ? labels[i] : '';
    return MediaQuery.withClampedTextScaling(
      maxScaleFactor: 1.2,
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
                  icon: Padding(padding: const EdgeInsets.only(bottom: 2), child: FaIcon(tabs[i].icon, size: 20)),
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
                icon: FaIcon(tabs[i].icon, size: 19, color: SoukColors.inkFaint),
                selectedIcon: FaIcon(tabs[i].icon, size: 19, color: SoukColors.emerald),
                label: label(i),
              ),
          ],
        );
      }),
    );
  }
}
