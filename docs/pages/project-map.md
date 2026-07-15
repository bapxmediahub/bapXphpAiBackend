---
type: doc
title: Project Map
description: Route /admin/developer/project-map - owner/developer visibility into routes, controllers, services, views, and more.
category: page
---
# Project Map Page

Route: `/admin/developer/project-map`

Controller: `AdminController@projectMap`

Purpose: owner/developer visibility into navigation, routes, controllers, services, views, integrations, schema, storage files, tools, and validation status.

Key checks: use the map to select an affected relationship, inspect every primary source on that path, make the change, run `bapXphp update`, then run non-mutating `bapXphp ci` and exercise the affected page/navigation behavior. A gap is a prompt to inspect existing source behavior before creating anything.
