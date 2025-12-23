# thirdparty/

This directory contains **third-party plugins and themes** that were **not created by the FlatPress team**.

We host selected projects here—some of which are no longer actively maintained—to keep them **available** and, where possible, to ensure **compatibility with current FlatPress versions**.  
**All rights remain with the respective original authors.**

---

## Why does this directory exist?

The **flatpress-extras** repository collects extensions for FlatPress. To make it clearly visible which content is **not our own work**, third-party projects are stored under `thirdparty/`.

Goals:
- **Preserve** useful plugins/themes that might otherwise become hard to find
- Provide **compatibility updates** for current FlatPress versions (best effort)
- Maintain **transparency** about authorship and provenance

---

## What does “adopted” mean here?

Within `thirdparty/`, we may:
- apply small fixes (e.g., PHP compatibility, deprecations)
- include security or stability improvements
- add metadata (source links, author info, license notes)

We do **not** claim authorship of the original work.

> Note: Not every project will be actively maintained long-term. Updates are provided on a “best effort” basis and depend on maintainer availability.

---

## Copyright, Licenses & Attribution

- Each project remains the **property of its original author(s)**.
- We keep existing copyright headers.
- **License terms** must be respected.
- If present, original license files (`LICENSE`, `COPYING`, etc.) remain included.
- If no license is specified, we try to document the project’s origin; when in doubt, we treat it as **“all rights reserved”** and only make changes that are legally uncontroversial (or remove the project again).

### Expected per-project metadata (plugin/theme)
Whenever possible, each project should include at least:
- a `README` (or note file) stating:
  - project name
  - original author(s) / copyright
  - link to the original source (repository, forum thread, website)
  - license information (including link/text)
- the license file, if available

---

## For users

- Content in `thirdparty/` is **not official FlatPress team work**.
- We try to keep it compatible with current FlatPress versions, but **cannot guarantee** it.
- Before using, please review:
  - license terms
  - changelog/compatibility notes
  - known issues in this repository

---

## For original authors (reclaiming / maintainership)

If you are the original author and would like to **resume maintaining** your plugin/theme: you’re very welcome to do so.

Please contact us with a reasonable proof of authorship (e.g., access to the original repo/account, a verifiable signature/post in the original forum thread, etc.). We can then, for example:
- add you as a maintainer or move the project to a more appropriate location
- update ownership/attribution metadata
- contribute our changes back to your original project as PRs (if desired)

---

## Contact & reporting

- For bugs/compatibility issues: please open an **Issue** in this repository.
- For reclaim/author inquiries: also use **Issues** (or Discussions, if enabled) to reach out.

---

## Directory structure

Typical layout (example):

thirdparty/
├─ plugins/
│  └─ <plugin-name>/
└─ themes/
   └─ <theme-name>/

Each project lives in its own subdirectory and should include a short description/attribution.

---

Thank you for using FlatPress—and thanks to all community authors whose work has enriched FlatPress over the years.
