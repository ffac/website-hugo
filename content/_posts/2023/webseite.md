---
title: "Firmware Release version 20230410"
date: 2023-04-10T22:40:00+02:00
author: djfe
---
Liebe Freifunk-Community,

mit diesem Blogartikel steht eine neue Freifunk Firmware in den Startlöchern.

Das Release 20230410 ist ein unfangreiches Firmware release welches einige
neuerungen und einige sehr wichtige security bug fixes hat. 

### Basisdaten:

 * Firmware-Version: 20230410
 * Gluon-Version: v2022.1.x
 * Commit ID: xxx
 * Download: https://community-build.freifunk-aachen.de

Folgende Gluon spezifischen Änderungen gab es unter anderen:

 * The Linux kernel was updated to version 4.14.275
 * The mac80211 wireless driver stack was updated to a version based on
   kernel 4.19.237
 * [SECURITY] Autoupdater: Fix signature verification.
 * [SECURITY] Config Mode: Prevent Cross-Site Request Forgery (CSRF).
 * Config Mode: Fix occasionally hanging page load after submitting the
   configuration wizard causing the reboot message and VPN key not to be
   displayed.
 * Config Mode (OSM): Update default OpenLayers source URL.
 * Config Mode (OSM): Fix error when using " character in attribution
   text.
 * respondd-module-airtime: Fix respondd crash on devices with disabled
   WLAN interfaces.
 * ipq40xx: Fix bad WLAN performance on Plasma Cloud PA1200 and PA2200 devices.
 * Fix occasional build failure in “perl” package with high number of threads (-j32 or higher).
 * status page: WLAN channel display does not require the respondd-module-airtime package anymore.
 * status page: The “gateway nexthop” label now links to the status page of the nexthop node.
 * status page: The timeout to retrieve information from neighbour nodes was increased, making the display of the name of overloaded, slow or otherwise badly reachable nodes more likely to succeed.