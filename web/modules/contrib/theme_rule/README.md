Description
-----------

The _Theme Rules_ module allows a site builder to show pages using different
themes based on _rules_. Each _rule_ contains the theme, and the conditions to
be met in order to show the that theme. Conditions are _condition plugins_, such
as page path pattern, current user role, current language or even, the type of
the node, when the page is a node canonical page. Meaning that custom
conditions may be added by contrib or custom modules, such as
https://www.drupal.org/project/route_condition.

Rules can be ordered by priority. The topmost rule whose conditions are met on a
certain context (page, user role, etc) wins, and the page is displayed with the
rule's configured theme. Rules can be reordered using drag and drop.

Usage
-----

* As a site builder, visit the _Appearance_ page and click the _Theme rules_
  tab.
* Use the _Add theme rule_ action button to add new rules.
* You can disable a rule so that it will be ignored on theme negotiation.
* A rule with no conditions is also ignored on theme negotiation.
