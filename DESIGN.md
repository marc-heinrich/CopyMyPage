# CopyMyPage Design System

## 1. Zweck und Geltungsbereich

Diese Datei definiert die verbindliche visuelle und interaktive Designsprache für neue und angepasste sichtbare CopyMyPage-Oberflächen in:

`C:\wamp\www\joomla6`

Sie ist kein Auftrag zur nachträglichen Vereinheitlichung bestehender Ansichten. Nicht vom aktuellen Auftrag betroffene Oberflächen bleiben unverändert. Bestehende spezialisierte UI-Verträge werden nur geändert, wenn dies ausdrücklich Bestandteil des Auftrags ist.

---

## 2. Quellen der Wahrheit

### Projektweite Designsprache

Diese Datei beschreibt visuelle Absicht, Hierarchie und allgemeine UI-Regeln.

### Formulare und reguläre Aktionsbuttons

Für Formulare, Formularzustände und reguläre Aktionsbuttons gilt zusätzlich:

`docs/UI_STYLE_GUIDE.md`

Diese Richtlinie hat dort Vorrang vor allgemeineren Aussagen dieser Datei.

### Konkrete Designwerte

Technische Quelle der Wahrheit sind die vorhandenen `:root`-Variablen und `--cmp-*`-Tokens in:

`C:\wamp\www\joomla6\media\com_copymypage\css\template.css`

Vorhandene Tokens wiederverwenden. Keine parallelen Farb-, Schatten-, Radius-, Typografie-, Spacing- oder Buttonsysteme einführen.

Fehlt für eine wiederkehrende semantische Designentscheidung ein geeigneter Token, prüfen, ob ein neuer `--cmp-*`-Token sinnvoller ist als ein lokal fest codierter Wert. Strukturwerte ohne wiederkehrende semantische Bedeutung dürfen lokal bleiben.

### Referenzformular

Für reguläre Formulare bleibt das Kontaktformular Referenz:

- Markup: `modules\mod_copymypage_contact\tmpl\contact_default.php`
- Felder: `modules\mod_copymypage_contact\forms\contact.xml`
- Darstellung: Bereiche `CONTACT` und `BUTTONS` in `template.css`

---

## 3. Designidentität

CopyMyPage soll ruhig, hochwertig, modern und inhaltsorientiert wirken.

Bevorzugt:

- klar statt dekorativ,
- hochwertig statt verspielt,
- ruhig statt visuell laut,
- großzügig statt gedrängt,
- präzise statt experimentell,
- modern ohne kurzlebige Trends,
- verständlich ohne unnötige Erklärung,
- technisch leistungsfähig ohne technisch kompliziert zu wirken.

Die Oberfläche stellt Inhalt und Benutzeraufgabe in den Mittelpunkt.

Visuelle Hierarchie entsteht primär durch:

1. Typografie,
2. Abstände,
3. Gruppierung,
4. Flächenkontrast,
5. Größe,
6. zurückhaltende Tiefe.

Starke Rahmen, intensive Schatten, dekorative Verläufe und übermäßige Farbe sind keine primären Strukturmittel.

---

## 4. Zentrale Prinzipien

### Content first

Jedes sichtbare Element benötigt einen funktionalen oder hierarchischen Zweck. Zusätzliche Oberfläche wird nicht allein deshalb ergänzt, weil Raum vorhanden ist.

### Ruhe durch Reduktion

Vor zusätzlichen Rahmen, Hintergrundfarben, Schatten, Icons, Trennlinien, Badges oder Containern prüfen, ob Typografie und Abstand denselben Zweck bereits erfüllen. Die einfachere Lösung bevorzugen.

### Konsistenz vor Originalität

Neue Komponenten sollen wie natürliche Bestandteile von CopyMyPage wirken. Keine eigene Button-, Formular-, Typografie-, Farb-, Radius-, Schatten- oder Spacing-Sprache je Ansicht einführen.

### Funktion vor Effekt

Animationen und visuelle Effekte müssen Zustand, Beziehung oder Funktion verdeutlichen. Reine Dekoration vermeiden.

### Progressive Gewichtung

Nicht alle Elemente dürfen gleich wichtig wirken. Typische Reihenfolge:

1. Seitentitel oder primärer Inhalt,
2. Hauptaktion,
3. unmittelbar relevante Inhalte,
4. sekundäre Aktionen,
5. Metadaten,
6. Hilfsinformationen.

---

## 5. Visuelle Richtung

Bevorzugt werden:

- helle, ruhige Oberflächen,
- großzügige Weißräume,
- klare Typografie,
- dezente Kontraste,
- zurückhaltende Schatten,
- kontrollierte Rundungen,
- wenige gezielt eingesetzte Akzentfarben.

Apple-inspirierte Prinzipien wie Inhaltsfokus, Weißraum, klare Hierarchie und ruhige Ebenen sind zulässig. Nicht automatisch übernehmen:

- großflächige Glaseffekte,
- starke Transparenz oder Blur,
- extreme Pillenformen,
- unnötig große Hero-Bereiche,
- dekorative Animationen.

Transparenz oder Blur nur bei funktionaler Ebenentrennung wie Navigation, schwebenden Werkzeugleisten, Overlays oder Dialoghintergründen einsetzen. Normale Inhaltskarten simulieren keine Glasflächen.

---

## 6. Typografie

Typografie ist ein zentrales Hierarchiemittel. Anzahl unterschiedlicher Größen, Gewichte und Stile begrenzen und vorhandene CopyMyPage-Tokens verwenden.

Grundregeln:

- Seitentitel besitzen die höchste textliche Hierarchie der Ansicht.
- Abschnittsüberschriften gliedern, ohne mit Seitentiteln zu konkurrieren.
- Fließtext erhält komfortable Zeilenhöhe und begrenzte Lesebreite.
- Labels, Buttons, Tabs und Controls verwenden die etablierte UI-Typografie.
- `--cmp-font-size-sm` bleibt Metadaten und untergeordneten Informationen vorbehalten; wichtige Labels oder Bedieninformationen nicht künstlich verkleinern.

---

## 7. Layout und Spacing

Layouts wirken luftig, stabil und vorhersehbar. Inhalte werden nicht unnötig über die volle Bildschirmbreite verteilt; große Displays dürfen Weißraum zeigen.

Textlastige Inhalte erhalten eine angenehme Maximalbreite. Formulare und Dialoge werden nicht allein wegen großer Displays breiter. Tabellen und Verwaltungsansichten dürfen verfügbaren Raum stärker nutzen.

Abstände bilden semantische Gruppen:

- innerhalb einer Komponente kleiner,
- zwischen Komponenten mittel,
- zwischen größeren Inhaltsbereichen größer.

Keine zufälligen Einzelwerte einführen, wenn ein vorhandener Design- oder Framework-Abstand denselben Zweck erfüllt.

Zusammengehörige Elemente stehen näher beieinander als unabhängige Bereiche. Nicht jedes Element benötigt einen eigenen Container.

---

## 8. Oberflächen, Rahmen, Schatten und Radien

Container nur einsetzen, wenn sie eine echte semantische oder visuelle Gruppe bilden. Struktur bevorzugt mit einem klaren Mittel herstellen: Abstand, leichter Flächenkontrast, dezenter Rahmen oder zurückhaltende Tiefe.

Rahmen sind Trennmittel, keine Standarddekoration.

Schatten zeigen primär Elevation, Überlagerung oder Fokus temporärer Oberflächen. Starke großflächige Drop-Shadows vermeiden.

Rundungen modern, aber kontrolliert einsetzen. Pillenformen eignen sich für Statusindikatoren, Chips, kleine Filter oder ausdrücklich pillenartige Controls; normale Karten, Dialoge, Formulare und Tabellen erhalten keine extremen Pillenradien.

---

## 9. Karten und Listen

Karten sind eigenständige Inhaltsgruppen mit:

1. klarem Inhalt,
2. erkennbarer Hierarchie,
3. nur notwendigen Aktionen.

Bevorzugt: großzügiges Padding, klare Titel, ruhige Oberfläche, geringe visuelle Komplexität.

Vermeiden: Rahmen + Schatten + farbigen Hintergrund gleichzeitig, übergroße Icons, unnötige Badges, mehrere gleich dominante Aktionen und dekorative Verläufe.

Eine gesamte Karte darf nur dann klickbar sein, wenn die ganze Fläche logisch eine einzige Aktion repräsentiert. Bei mehreren unabhängigen Aktionen bleiben diese einzeln bedienbar.

Listen und Tabellen müssen schnell scanbar sein. Spalten, Trennlinien und Hintergründe nur einsetzen, wenn sie Orientierung verbessern.

---

## 10. Navigation

Navigation muss gegenüber Inhalt eindeutig erkennbar sein, ohne ihn zu dominieren.

- Aktive Einträge erhalten einen klaren Zustand.
- Fokus und Hover bleiben unterscheidbar.
- Zustände dürfen nicht ausschließlich über Farbe kommuniziert werden.
- Navigation darf als funktionale Ebene stärker abgesetzt sein als normale Inhaltsflächen.
- Transparenz oder Blur sind zulässig, wenn Lesbarkeit und Kontrast erhalten bleiben.

Kontextuelle Zurücknavigation folgt dem projektweiten `cmp-button--back`-Vertrag aus `docs/UI_STYLE_GUIDE.md`. Position und Ziel bleiben ansichtsspezifisch; Darstellung nicht lokal neu erfinden.

### Grouped Chevron Navigation

Verbindliche visuelle Referenz ist `view=dashboard`, `layout=profile`: etwa die zusammenhängenden Zeilen „Persönliche Angaben“ und „Adresse“. Diesen Standard für verwandte Navigations- und Einstellungsaktionen wiederverwenden, statt einzelne lokale Kartenvarianten anzulegen.

- Eine Gruppe verwendet `ul.cmp-dashboard-link-group__list` und direkte `li.cmp-dashboard-link-group__item`. Die Liste besitzt eine gemeinsame Fläche mit `--cmp-color-surface`, keine Zwischenabstände und ausschließlich äußere Rundungen über `--cmp-border-radius-lg`. Zwischen Geschwisterzeilen liegt der bestehende Divider: `var(--cmp-border-width) solid rgba(var(--cmp-color-logo-dark-rgb), 0.12)`.
- Die Hauptaktion verwendet `cmp-dashboard-link-group__link`: Grid, mindestens 4 rem Zeilenhöhe, Padding und Gap aus `--cmp-spacing-sm` / `--cmp-spacing-md`, Schrift aus `--cmp-font-family-body-alt` und Text aus `--cmp-color-logo-dark`. `cmp-dashboard-link-group__copy` bündelt den kräftigen Titel (`__label`) und optionalen Sekundärtext (`__description`). Beschreibung und Metadaten verwenden `--cmp-font-size-sm`, `--cmp-color-text-muted`, normale Schriftstärke und Zeilenhöhe 1,5.
- Ein linkes `cmp-dashboard-link-group__icon` ist optional. Es verwendet die vorhandene 2,5-rem-Fläche, `--cmp-color-strong` und `--cmp-color-background-default`. Ohne Icon verwendet die Zeile `cmp-dashboard-link-group__link--text`. Die MFA-Methodenliste verwendet bewusst keine Methodenicons; andere Dashboardgruppen behalten ihre vorhandenen Icons.
- Rechts steht `cmp-dashboard-link-group__chevron` mit dem dekorativen UIkit-`chevron-right`; seine Farbe ist `--cmp-color-text-muted`. Das Chevron bleibt bei mehrzeiligem Text stabil ausgerichtet. `minmax(0, 1fr)`, `min-inline-size: 0` und `overflow-wrap: anywhere` sichern kontrolliertes Wrapping.
- Hover und Active behalten Textfarbe und ruhige Fläche der bestehenden Profilimplementierung. Tastaturfokus erhält den nach innen gerichteten Rahmen aus `--cmp-border-width-lg` und `--cmp-color-strong`. Die vorhandenen kurzen Farb-/Flächenübergänge berücksichtigen Reduced Motion.
- Die gesamte Zeile bildet grundsätzlich eine zugängliche Navigationsfläche. Native Links, aussagekräftige Namen und zugehörige Beschreibungen erhalten; dekorative Icons vor Screenreadern verbergen. Auf kleinen Displays reduzieren die vorhandenen `body.is-small`-Regeln Padding und Gap, ohne Text abzuschneiden oder Touchziele zu verkleinern. Mobile-first auch bei 320 px prüfen.
- Optionale Statuskennzeichnungen stehen beim Titel und verwenden tatsächliche Fachzustände, sichtbaren Text und vorhandene Signalfarben. In der MFA-Liste erscheint „Aktiv“ in der Success-Farbe, „Standard“ neutraler. Metadaten bleiben unterhalb der Beschreibung erhalten. Keine Statuswerte allein über Farbe kommunizieren.
- Bei zusätzlichen unabhängigen Aktionen verwendet ein gemeinsamer Zeilencontainer `cmp-dashboard-link-group__link--split`. Der native Hauptlink `cmp-dashboard-link-group__primary` erweitert seine Fläche per Pseudoelement über die Zeile und erhält dort seinen sichtbaren Fokusrahmen. Eine separate Aktion `cmp-dashboard-link-group__action` liegt als eigenständiger Link oder Button außerhalb des Hauptlinks und über dessen erweiterter Fläche. Ihr Zielbereich beträgt mindestens 2,75 rem in beiden Richtungen. Im MFA-Beispiel ist „Löschen“ ein kompakter roter Textlink beim Status mit eigenem Fokus und der unveränderten Joomla-Bestätigung.
- Status und Löschaktion dürfen die Semantik des Navigationslinks nicht verletzen: keine verschachtelten interaktiven Elemente, kein gemeinsamer Klickhandler für Bearbeiten und Löschen, keine versehentliche Löschaktion beim Öffnen der Zeile. Lange Titel, Status und separate Aktionen müssen kontrolliert umbrechen. Nicht bearbeitbare Datensätze bleiben Information und erhalten kein irreführendes Navigationschevron.

---

## 11. Formulare und Aktionen

Für technische Formular- und Buttondetails gilt `docs/UI_STYLE_GUIDE.md`.

Allgemein:

- Formulare ruhig, klar gegliedert und gut scanbar gestalten.
- Zusammengehörige kurze Felder dürfen nebeneinander stehen; längere oder komplexere Eingaben bevorzugt untereinander.
- Eine Aktionsgruppe besitzt normalerweise höchstens eine visuell dominante Hauptaktion.
- Sekundäre Aktionen dürfen nicht mit der Hauptaktion konkurrieren.
- Destruktive Aktionen müssen eindeutig als solche erkennbar sein.
- Funktionale Joomla-, UIkit- und Bootstrap-Verträge sowie Plugin-Hooks nicht allein für die Optik entfernen oder umdeuten.

---

## 12. Dialoge, Hinweise und Status

Dialoge nur verwenden, wenn der aktuelle Arbeitsfluss bewusst unterbrochen werden muss. Aufgaben, die sinnvoll inline gelöst werden können, nicht unnötig in Modals verschieben.

Ein Dialog enthält typischerweise:

1. eindeutigen Titel,
2. kurze Erklärung oder Inhalt,
3. klare Aktionsgruppe.

Destruktive Bestätigungen benennen eindeutig, was verändert oder gelöscht wird. Dialoge nicht unnötig breit machen; große Formulare oder komplexe Abläufe gehören auf eigene Ansichten.

Hinweise und Statusmeldungen müssen semantisch verständlich sein. Erfolg, Information, Warnung und Fehler nicht nur über Farbe unterscheiden. Text und gegebenenfalls Icon müssen denselben Zustand vermitteln.

---

## 13. Icons

Icons haben eine klare Funktion: Orientierung, Zustand oder kompakte Aktion. Keine dekorative Icon-Dichte ohne Nutzen.

- Dekorative Icons erhalten `aria-hidden="true"`.
- Reine Icon-Aktionen benötigen einen zugänglichen Namen.
- Icon und Text dürfen sich semantisch nicht widersprechen.
- Bestehende CopyMyPage-/UIkit-Iconverträge wiederverwenden, wenn sie passen.

---

## 14. Interaktionszustände und Fokus

Interaktive Elemente müssen je nach Komponente sinnvolle Zustände besitzen:

- Normal,
- Hover,
- Fokus,
- Active/Selected,
- Invalid,
- Disabled,
- Loading.

Zustände sollen stabil und vorhersehbar sein. Hover darf keine Information enthalten, die Tastatur- oder Touchbenutzern fehlt.

Fokus bleibt deutlich sichtbar und wird nicht aus optischen Gründen entfernt. `:focus-visible` und bestehende Accessibility-Verträge erhalten.

Disabled-Elemente müssen funktional und visuell deaktiviert sein; `aria-disabled` allein ersetzt nicht automatisch die notwendige technische Sperre.

---

## 15. Motion

Bewegung dient Orientierung und Zustandswechsel, nicht Dekoration.

- kurze, zurückhaltende Übergänge,
- keine unnötigen Daueranimationen,
- keine Bewegung, die Inhaltserfassung erschwert,
- `prefers-reduced-motion` berücksichtigen.

Wenn ein Zustand ohne Animation gleich verständlich bleibt, ist die reduzierte Darstellung zulässig.

---

## 16. Responsive Verhalten und Touch

Responsive Design erhält Inhalt, Hierarchie und Funktion statt nur Elemente zu verkleinern.

Auf kleinen Displays insbesondere prüfen:

- sinnvolle Stapelung,
- lesbare Textbreite,
- keine horizontalen Seitenüberläufe,
- klare Aktionsreihenfolge,
- ausreichend große Touchziele,
- keine ausschließlich hoverabhängige Bedienung.

Breite, komplexe Inhalte dürfen einen lokal begrenzten eigenen Scroll-/Pan-Bereich erhalten, wenn die Gesamtseite dadurch nicht horizontal überläuft.

Der gemeinsame Zielbereich für reguläre Controls soll mindestens etwa 44 px beziehungsweise 2,75 rem entsprechen, sofern kein bestehender spezialisierter Vertrag etwas anderes vorsieht.

### Verbindlicher Viewport-/Breakpoint-Standard

Für neue und ausdrücklich beauftragte responsive Anpassungen gilt der vorhandene CopyMyPage-Viewport-Vertrag. Bestehende Sonderfälle werden dadurch nicht pauschal zu Fehlern und nicht automatisch umgestellt.

#### Zentrale Definitionen

Technische Quelle der Wahrheit ist `CopyMyPage.constants.VIEWPORT_QUERY_DEFINITIONS` in der Live-Datei `media/com_copymypage/js/copymypage-base.js`. `ViewportFeature` in `media/com_copymypage/js/copymypage-behavior.js` wertet diese Definitionen mit `window.matchMedia()` aus und setzt die Klasse auf dem jeweiligen `<body>`.

| Body-Klasse | Definierte Bedingung / vorgesehener Bereich in CSS-Pixeln |
| --- | --- |
| `is-small` | `max-width: 639.98px` |
| `is-narrow` | `min-width: 640px` und `max-width: 767.98px` |
| `is-mobile` | `min-width: 768px` und `max-width: 959.98px` |
| `is-tablet` | `min-width: 960px` und `max-width: 1199.98px` |
| `is-desktop` | vorgesehen ab 1200 px; aktuell der Restfall, wenn keine der vier anderen Bedingungen zutrifft |

`is-mobile` bezeichnet ausschließlich den Bereich 768–959,98 px. Es ist keine Sammelklasse für Smartphones, Touchgeräte oder alle kleinen Viewports. Der zusätzliche JavaScript-Zustand `uiDesktop` verwendet `min-width: 960px`; er umfasst regulär Tablet und Desktop und erzeugt keine sechste Body-Klasse.

Die aktuelle Implementierung enthält keine eigene Desktop-Media-Query. Zwischen den jeweiligen Maximalwerten mit `.98` und der folgenden ganzzahligen Minimalgrenze liegen rechnerisch offene Intervalle: `(639.98, 640)`, `(767.98, 768)`, `(959.98, 960)` und `(1199.98, 1200)`. Falls die Media-Query-Auswertung dort keine der vier Bereichsbedingungen erfüllt, wird ebenfalls `is-desktop` gesetzt. Rundung durch den Browser berücksichtigen; Klassenkombinationen deshalb nicht ungeprüft als exakt gleichwertig zu einer durchgehenden nativen Breitenabfrage behandeln. Eine Änderung dieser Grenzen benötigt einen eigenen Auftrag.

#### Initialisierung, Aktualisierung und Dokumentkontext

- Die normale Seitenschale `templates/copymypage/index.php` lädt das Preset `copymypage.site`. Dessen JavaScript-Abhängigkeiten werden mit `defer` geladen. `CopyMyPageAssetItem.php` startet die Runtime bei `DOMContentLoaded`, wenn das Dokument noch lädt, andernfalls unmittelbar. Die Runtime in `copymypage.js` initialisiert `ViewportFeature`.
- Bei Initialisierung und jedem `MediaQueryList`-`change` entfernt das Feature alle fünf bekannten Viewport-Klassen und fügt genau eine aktuelle Klasse hinzu. Es verwendet `addEventListener('change', ...)`, ersatzweise `onchange`. Innerhalb eines unveränderten Bereichs ist kein Klassenwechsel erforderlich. Ein `resize`- oder `visualViewport`-Listener ist dafür nicht implementiert.
- Genau eine Klasse gilt nach erfolgreicher Initialisierung und Aktualisierung bei vorhandenem Body. Im serverseitigen Markup, vor der Initialisierung sowie bei deaktiviertem, blockiertem oder fehlerhaftem JavaScript ist keine Viewport-Klasse garantiert. Die Klassen sind insbesondere kein garantierter First-Paint-Vertrag; ein Preloader ersetzt diese Garantie nicht.
- `joomla:updated` startet die globale Viewport-Runtime nicht neu. Neue Inhalte im selben Body nutzen dessen bestehende Klasse. Nach Body-Austausch oder Runtime-Teardown muss die zuständige Integration die Initialisierung wiederherstellen; native Media Queries benötigen diesen Lebenszyklus nicht. Aktuell entfernt `pagehide` die Runtime-Listener, ohne dass der Initializer eine `pageshow`-Wiederaufnahme definiert. Rückkehr aus dem Back/Forward Cache muss bei klassenabhängigen Änderungen gesondert geprüft werden.
- Login und weitere Authentifizierungsansichten innerhalb von `index.php` nutzen dieselbe Runtime. `error.php`, `component.php` und `offline.php` stellen selbst keinen Viewport-Klassenvertrag bereit. Komponenten können weitere Assets anfordern; darauf nicht ohne Prüfung vertrauen.
- Ein Fragment-Drawer liegt im Body der Hauptseite und nutzt dessen Klasse. Ein Dokument-Drawer verwendet ein eigenes iframe-Dokument mit eigener Viewport-Breite. Es erbt weder Body-Klassen noch Runtime der Hauptseite; `component.php` initialisiert diese Runtime selbst nicht. Der responsive Vertrag des äußeren Drawers darf nicht auf das innere Dokument übertragen werden.

#### Verwendungsrichtlinien

- Bei allgemeinen viewportabhängigen Layoutanpassungen vorhandene Klassen bevorzugen, sofern Bereich, Dokumentkontext, Initialisierung und JavaScript-Abhängigkeit zur Aufgabe passen. Mehrere vorhandene Klassen kombinieren, statt eine zweite konkurrierende Standard-Breakpoint-Systematik anzulegen.
- Mobile-first und fluides CSS bevorzugen, wenn eine brauchbare Grunddarstellung, Flex-Wrapping, Grid mit passenden Mindestbreiten oder `clamp()` die Komponente vereinfachen. Ein diskreter Struktur- oder Inhaltswechsel lässt sich nicht allein durch `clamp()` ersetzen.
- Komponentenbezogene Sonder-Breakpoints sind zulässig, wenn Platzbedarf, Textlängen, Controls oder Einbettung sie begründen. Bei containerabhängigem Platzbedarf eine Container Query prüfen; dafür ist ein geeigneter Container-Vertrag erforderlich. Die Einführung eines Containers ist keine reine Selektoränderung.
- Native `@media (min-width/max-width)`-Regeln beibehalten, wenn das Layout bereits beim First Paint, ohne JavaScript oder in Dokumenten ohne Viewport-Runtime funktionieren muss. Auch eine passende Standardgrenze rechtfertigt dort keinen automatischen Austausch.
- Accessibility- und Feature Queries wie `prefers-reduced-motion`, `orientation`, `hover`, `pointer` sowie `@supports` erhalten ihren jeweiligen Zweck. Sie werden nicht durch Breitenklassen ersetzt.
- CSS-Spezifität und Cascade vor jeder Umstellung prüfen. `@media` erhöht die Selektorspezifität nicht; ein zusätzliches `body.is-small` beziehungsweise `body:is(...)` tut dies. Bestehende Komponenten-, Zustands-, Framework- und nachfolgende Sonderregeln müssen weiterhin wie vorgesehen gewinnen. Quellreihenfolge allein gleicht höhere Spezifität nicht aus.
- Keine pauschalen Pixelverbote. Entscheidend sind der nachgewiesene Layoutzweck und die technische Zuverlässigkeit. `rem`-Grenzen in Media Queries beziehen sich auf die initiale Browser-Schriftgröße, nicht auf die per CSS oder `--cmp-font-size` gesetzte Root-Schriftgröße; sie sind deshalb nicht universell mit festen Pixelgrenzen gleichzusetzen.

#### Selektorbeispiele und Ausnahmen

Die folgenden Formen entsprechen den vorhandenen Projektkonventionen; sie setzen einen Body mit korrekt initialisierter Viewport-Klasse voraus:

```css
body.is-small .cmp-example {
    padding-inline: var(--cmp-spacing-md);
}

body:is(.is-small, .is-narrow) .cmp-example {
    grid-template-columns: minmax(0, 1fr);
}
```

Für die regulären Bereiche bis 959,98 px `is-small`, `is-narrow` und `is-mobile` kombinieren; für die regulären Bereiche ab 960 px `is-tablet` und `is-desktop`. Die oben beschriebenen Restfallintervalle und die Verfügbarkeit der Klassen bleiben zu beachten. `body:not(.is-small)` ist kein verlässlicher Ersatz für `min-width: 640px`: Der Selektor trifft auch vor der Initialisierung und ohne JavaScript zu.

Bei einem ausdrücklich beauftragten Austausch kann `:where(body.is-small) .cmp-example` die ursprüngliche Spezifität von `.cmp-example` erhalten. Der gesamte zusätzliche Body-Filter muss dafür in `:where()` stehen; `body:where(.is-small)` behält die zusätzliche Typ-Spezifität von `body`. Dies löst weder Initialisierungs- noch Fallback-Probleme und ist kein pauschaler Ersatz für die bestehenden Selektoren.

Zusätzliche native Breitenabfragen sind insbesondere für sehr schmale Displays, Komponenten mit eigenem Platzbedarf, Schriftgrößen-bezogene Layoutgrenzen und JavaScript-unabhängige Login-, Fehler- oder Dokumentdarstellungen erlaubt. Vor ihrer Einführung Zweck und Abweichung vom Klassenbereich nachvollziehbar begründen. Bestehende Ausnahmen erst nach gezielten Responsive-Tests konsolidieren.

Bei späteren Umstellungen beide Seiten der relevanten Grenzen, verzögertes und deaktiviertes JavaScript, Browser-Zoom, dynamische Viewport-Wechsel sowie die betroffenen Dokumentkontexte prüfen. Mobile Safari zusätzlich mit Rotation, Pinch-Zoom, Browserleisten beziehungsweise Tastatur und Rückkehr aus dem Back/Forward Cache prüfen; Änderungen des sichtbaren Viewports erzwingen nicht automatisch einen Wechsel der Layout-Viewport-Klasse.

---

## 17. Barrierefreiheit

Barrierefreiheit ist Teil des Designvertrags.

Erhalten beziehungsweise gezielt einsetzen:

- semantische HTML-Struktur,
- Labels und programmatische Namen,
- ARIA nur dort, wo native Semantik nicht ausreicht,
- sichtbare Fokuszustände,
- Tastaturbedienbarkeit,
- Disabled- und Invalid-Semantik,
- Screenreader-Texte und versteckte Legenden,
- ausreichenden Kontrast,
- nicht-farbige Zustandsindikatoren.

Keine bestehende Accessibility-Struktur aus rein optischen Gründen entfernen.

---

## 18. Framework-Regeln

CopyMyPage verwendet projektkontrollierte Templates und vorhandene Frameworkverträge. Joomla-, UIkit- und Bootstrap-Klassen können funktionale Bedeutung besitzen.

- Nicht allein für optische Vereinheitlichung entfernen.
- UIkit und Bootstrap nicht ohne bestehenden Grund auf demselben Control vermischen.
- Globale Frameworkregeln nicht überschreiben, wenn eine komponentenspezifische Regel ausreicht.
- Bestehende Plugin-Hooks, `data-*`-Attribute und JavaScript-Verträge erhalten.

### UIkit-Accordions

Für CopyMyPage-UIkit-Accordions mit klar getrenntem Header- und Content-Bereich gilt:

- Zustandsindikatoren verwenden den bestehenden CopyMyPage-Chevron-Vertrag. Plus-/Minus-Darstellungen nicht parallel neu einführen, sofern keine fachliche Ausnahme besteht.
- Im geöffneten Zustand trennt ein durchgehender horizontaler Divider Header und Content.
- Der Divider läuft über die gesamte verfügbare Breite des Accordion- beziehungsweise Card-Bereichs und wird nicht mit dem horizontalen Content-Padding eingerückt.
- Der eigentliche Content behält seine vorgesehenen Innenabstände; Divider und Content-Einrückung werden getrennt behandelt.
- Für Farbe und Gewicht den bestehenden gemeinsamen Divider-Vertrag verwenden; die aktuelle Referenz ist `1px solid rgba(var(--cmp-color-logo-dark-rgb), 0.12)` entsprechend `orderreview`.
- Vollbreite Divider innerhalb des animierten Accordion-Contents nicht über negative horizontale Margins aus dem Content herausziehen. UIkit verwendet während der Öffnungs- und Schließanimation temporär einen Wrapper mit `overflow: hidden`; überstehende Inhalte können dadurch während der Animation abgeschnitten werden.
- Header- und Content-Padding so strukturieren, dass der Divider innerhalb der tatsächlich animierten Wrapper-Breite vollbreit bleibt, während der eigentliche Content seine vorgesehenen horizontalen Innenabstände behält.
- Keine JavaScript-Timing-Workarounds wie `setTimeout` verwenden, um rein visuelle Accordion-Zustände zu synchronisieren. Layout und Divider müssen über den bestehenden HTML-/CSS-Vertrag stabil sein.
- Keine voneinander abweichenden Divider-Lösungen je View anlegen. `ticketselection` und `seatselection` sind die bestehenden Implementierungsreferenzen; das MFA-Accordion in `cmp-account-security` ist Referenz für animationsstabile vollbreite Divider und den Chevron-Vertrag.

---

## 19. Keine lokalen Designinseln

Eine neue Ansicht darf nicht ihr eigenes Mini-Designsystem etablieren. Vor neuen lokalen Farben, Radien, Schatten, Typografien oder Buttonstilen prüfen:

1. existiert bereits ein passender CopyMyPage-Token?
2. existiert bereits eine passende gemeinsame Komponente?
3. ist die Abweichung fachlich erforderlich?

Nur wenn die Abweichung wirklich komponentenspezifisch ist, lokal kapseln.

---

## 20. Neue Komponenten

Bei neuen sichtbaren Komponenten in dieser Reihenfolge vorgehen:

1. Aufgabe und Informationshierarchie klären,
2. vorhandene CopyMyPage-Komponenten und Tokens prüfen,
3. bestehende Frameworkverträge erhalten,
4. kleinstmögliche neue Struktur ergänzen,
5. Desktop und kleines Display prüfen,
6. Tastatur-, Fokus- und relevante Statuszustände prüfen.

Keine allgemeine Modernisierung angrenzender UI-Bereiche mitziehen.

---

## 21. Qualitätsprüfung bei UI-Änderungen

Nur aufgabenrelevante Prüfungen durchführen.

### Desktop

- Hierarchie und Lesbarkeit,
- Abstände und Ausrichtung,
- Aktionshierarchie,
- konsistente Oberfläche.

### Kleines Display

- Umbrüche und Stapelung,
- Touchziele,
- horizontale Überläufe,
- Aktionsreihenfolge,
- Nutzbarkeit ohne Querformatzwang.

### Interaktion

Je nach Änderung:

- Hover,
- Fokus,
- Active/Selected,
- Invalid,
- Disabled,
- Loading,
- Tastaturbedienung,
- Reduced Motion,
- bei animierten Komponenten auch sichtbare Zwischenzustände während Öffnen, Schließen oder Transition prüfen; nicht nur Start- und Endzustand.

### CSS

Vorhandene `--cmp-*`-Tokens verwenden. Source-/Min-Vertrag aus `AGENTS.md` beachten. Nach Umsetzung von GitHub #134 gilt der dort dokumentierte kanonische CSS-Build.

---

## 22. Entscheidungsregel

Wenn mehrere visuelle Lösungen fachlich gleichwertig sind, bevorzuge diejenige, die:

1. weniger neue Regeln benötigt,
2. vorhandene CopyMyPage-Komponenten und Tokens wiederverwendet,
3. die Hierarchie klarer macht,
4. auf kleinen Displays stabil bleibt,
5. leichter zugänglich und wartbar ist.

---

## 23. Zielbild

CopyMyPage soll über unterschiedliche Komponenten hinweg wie ein zusammenhängendes Produkt wirken: ruhig, klar, konsistent, zugänglich und auf die eigentliche Aufgabe des Benutzers konzentriert.
