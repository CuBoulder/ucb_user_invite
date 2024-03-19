# CU Boulder User Invite

All notable changes to this project will be documented in this file.

Repo : [GitHub Repository](https://github.com/CuBoulder/ucb_user_invite)

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

- ### CU Boulder User Invite v1.1
  This update:
  - [New] Enables selection of multiple user roles for an invite. Previously only one role could be selected.
  - [New] Adds role descriptions, which can be edited in settings.
  - [Change] Updates the description for the _User IdentiKeys_ and _Custom message_ fields.
  - [Change] Changes the namespace of the CU Boulder User Invite settings.
  
  Update hook `ucb_user_invite_update_9501`:
  - Migrates the settings from the old namespace to the new one.
  
  Resolves CuBoulder/ucb_user_invite#6
  
  Sister PR in: [tiamat10-profile](https://github.com/CuBoulder/tiamat10-profile/pull/103)
---

- ### Fixes error on sending invites
  Resolves CuBoulder/ucb_user_invite#4
---

- ### Adds `CHANGELOG.MD` and workflows; updates `core_version_requirement` to indicate D10 compatibility
  Resolves CuBoulder/ucb_user_invite#2
---
