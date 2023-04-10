# Freifunk Nordwest Website
Das Repository [ffac-website](https://github.com/ffac/website-hugo/) enthält den Inhalt der static-site konfigurierten Hugo Webseite.
Diese basiert auf dem Hugo Template vom FFNW.

Untegliedert ist die Website in:
* [Website](https://github.com/ffac/website-hugo)
* [Theme](https://git.ffnw.de/ffnw-website/theme)

## Theme und Posts testen
Um eine bestimmte Version des Themes oder der Posts zu testen, können folgende Befehle genutzt werden. Hier beispielsweise für den main-Branch.
```sh
hugo mod get git.ffnw.de/ffnw-website/theme@main
```

Eine Preview kann mit `hugo serve` gestartet werden.
Damit drafts auch gerendered werden wird `-D` beigefügt.

Zum Build wird schlicht `hugo` ausgeführt, anschließend kann man den public folder durch einen Webserver bereitstellen.
