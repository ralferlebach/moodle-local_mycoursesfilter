# local_mycoursesfilter

`local_mycoursesfilter` stellt eine eigenständige, direkt verlinkbare Moodle-Seite bereit, die nur die Kurse der aktuell angemeldeten Person anzeigt und dabei gezielt vorfiltert.

Die Seite eignet sich für Dashboard-Links, Kurslinks, Portaleinstiege und andere Stellen, an denen nicht die allgemeine Core-Seite **Meine Kurse**, sondern eine vorgefilterte Sicht benötigt wird.

## Zweck des Plugins

Das Plugin zeigt ausschließlich Kurse an, in denen die aktuelle Person eingeschrieben ist, und bietet dafür eine Moodle-nahe Bedienoberfläche mit:

- Filterung nach Kursstatus, Favoriten und verborgenen Kursen
- Suche nach Kursnamen
- Sortierung der Trefferliste
- Umschaltung zwischen Karten-, Listen- und Beschreibungsansicht
- optionalem Zurück-Button zur aufrufenden Seite

## Installation

1. Plugin nach `local/mycoursesfilter` kopieren
2. **Website-Administration → Mitteilungen** aufrufen
3. Plugin-Einstellungen prüfen
4. Links auf `/local/mycoursesfilter/index.php` an geeigneten Stellen hinterlegen

## Unterstützte URL-Parameter

Basis-URL:

```text
/local/mycoursesfilter/index.php
```

| Parameter | Bedeutung |
|---|---|
| `coursename` | Teilstring-Suche in Kursvollname und Kurzname |
| `tag` | Kurs-Tag als Name oder numerische Tag-ID |
| `catid` | Kategorieauswahl als einzelne ID, kommaseparierte ID-Liste oder `this`, `parent`, `children` |
| `customfield` | Customfield-Filter als `shortname` oder `shortname:wertteil` |
| `filter` | Einer von `all`, `notstarted`, `inprogress`, `completed`, `favourites`, `hidden` |
| `sort` | Einer von `lastaccess`, `coursename`, `shortname`, `lastenrolled` |
| `sortorder` | `asc` oder `desc`; wenn leer, wird die Standardsortierung automatisch gewählt |
| `view` | Einer von `card`, `list`, `summary` |
| `returnurl` | Optionales lokales Rücksprungziel; akzeptiert auch `this` |
| `title` | Optionaler Seitentitel, sofern URL-Überschreibung in den Einstellungen erlaubt ist |
| `courseid` | Optionale Quell-Kurs-ID für kontextabhängige Kategorieauflösung |
| `only` | Erzwingt nicht-rekursive Kategorieauswahl |
| `recursive` | Erzwingt rekursive Kategorieauswahl |

## Standardverhalten

### Kursmenge

Angezeigt werden nur Kurse, in denen die aktuelle Person tatsächlich eingeschrieben ist.

### Namenssuche

`coursename` prüft per Teilstring-Suche sowohl den Kursvollnamen als auch den Kurznamen.

### Kategorien

`catid` unterstützt:

- einzelne IDs, zum Beispiel `catid=2`
- kommaseparierte ID-Listen, zum Beispiel `catid=2,3,4,5`
- Schlüsselwörter:
  - `this` = Kategorie des Quellkurses
  - `parent` = übergeordnete Kategorie des Quellkurses
  - `children` = direkte Unterkategorien der Quellkurs-Kategorie
- Mischformen, zum Beispiel `catid=parent,12,children`

Standardmäßig richtet sich die Rekursion nach der Instanzeinstellung:

- **recursive**: ausgewählte Kategorien plus alle Unterkategorien
- **only**: nur die ausdrücklich ausgewählten Kategorien

Pro Aufruf kann das Verhalten überschrieben werden:

- `only=1` deaktiviert Rekursion
- `recursive=1` erzwingt Rekursion

### Customfields

`customfield` unterstützt zwei Formen:

- `customfield=deliverymode`
  - trifft, wenn das Feld `deliverymode` einen nicht-leeren Wert hat
- `customfield=deliverymode:online`
  - trifft, wenn der Feldwert `online` als Teilstring enthält

### Standardsortierung

Wenn `sortorder` nicht gesetzt ist, verwendet das Plugin automatisch:

- `lastaccess` → `desc`
- `lastenrolled` → `desc`
- `coursename` → `asc`
- `shortname` → `asc`

### Titelauflösung

Die Überschrift wird in dieser Reihenfolge bestimmt:

1. `title` aus der URL, falls in den Einstellungen erlaubt
2. hinterlegter Standardtitel aus den Plugin-Einstellungen
3. Sprachstring des Plugins

### Rücksprung-Link

Wenn `returnurl` gültig ist, wird ein Zurück-Button angezeigt.

Erlaubt sind:

- ein explizites lokales Ziel, zum Beispiel `returnurl=%2Fmy%2Fcourses.php`
- `returnurl=this`, um auf die lokal geprüfte aufrufende Seite zurückzuspringen

## Instanzweite Einstellungen

Das Plugin bietet folgende globale Einstellungen:

- **Persistenz der Werkzeugleiste**
  - **Nicht persistieren**: Filter, Sortierung und Ansicht gelten nur für den aktuellen Request
  - **Core-Preferences wiederverwenden**: kompatible Einstellungen werden auf die Core-Preferences von **Meine Kurse** gemappt
- **Standard-Scope für Kategorien**
  - **Rekursiv**: ausgewählte Kategorien schließen Unterkategorien mit ein
  - **Nur ausgewählte Kategorien**: keine automatische Erweiterung
- **Standardtitel der Seite**
  - wenn leer, wird automatisch der Sprachstring verwendet
- **Titel aus URL erlauben**
  - steuert, ob `title` den Standardtitel überschreiben darf

## Beispiele

Nach Kursname filtern:

```text
/local/mycoursesfilter/index.php?coursename=biology
```

Mehrere Kategorien gleichzeitig verwenden:

```text
/local/mycoursesfilter/index.php?catid=2,3,4,5
```

Kontextabhängige Kategorie und Rücksprung zur aufrufenden Seite:

```text
/local/mycoursesfilter/index.php?catid=parent&returnurl=this
```

Rekursion für Kategorien abschalten:

```text
/local/mycoursesfilter/index.php?catid=12&only=1
```

Seitentitel per URL überschreiben:

```text
/local/mycoursesfilter/index.php?filter=inprogress&title=Aktuelle%20Lernphase
```

Customfield per Teilstring filtern:

```text
/local/mycoursesfilter/index.php?customfield=deliverymode:online
```

## Grenzen und erwarteter Aufrufkontext

- `returnurl=this` funktioniert nur dann sinnvoll, wenn die Seite aus einer lokalen Moodle-Seite aufgerufen wurde und der Referrer auf dieselbe Instanz zeigt.
- `catid=this`, `catid=parent` und `catid=children` benötigen einen Aufrufkontext, aus dem ein Quellkurs bestimmt werden kann. Das geschieht entweder:
  - direkt über `courseid`, oder
  - über einen lokalen Referrer auf `/course/view.php?id=...`
- Ungültige oder nicht unterstützte Parameterwerte werden verworfen oder auf sichere Standardwerte zurückgeführt.
- `returnurl` akzeptiert nur lokale Ziele derselben Moodle-Instanz.

## Sicherheit der URL-Parameter

Die URL-Parameter werden vor der Verwendung validiert und normalisiert. Dazu gehören insbesondere:

- feste Allow-Lists für `filter`, `sort`, `sortorder` und `view`
- strikte Prüfung von `catid` auf erlaubte IDs und Schlüsselwörter
- sichere Behandlung freier Texteingaben
- lokale Prüfung von `returnurl`
- Same-Site-Prüfung für `returnurl=this`

Nicht unterstützte oder ungültige Werte werden ignoriert.
