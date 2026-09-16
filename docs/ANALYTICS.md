# Analytics layer

The application stores the calendar. This layer answers questions about it.

`sql/002_bi.sql` creates a set of **read-only views** shaped as a star schema,
so Power BI, Metabase, Excel or any SQL client can be pointed at the database
and build a model without writing joins. The views touch nothing: they can be
created and dropped without any risk to the application.

```bash
mysql -u root calendario_demo < sql/002_bi.sql
```

## The model

| View | Grain | Answers |
|---|---|---|
| `bi_hechos_eventos` | one row per event | how many, of what kind, how big |
| `bi_hechos_dias` | one row per event **and day** | where the workload actually falls |
| `bi_puente_procedencia` | one row per event and origin | many-to-many audience origins |
| `bi_hechos_actividad` | one row per audit entry | who uses the tool, and how much |
| `bi_dim_*` | one row per catalogue value | the slicers |
| `bi_plano_eventos` | one row per event, fully joined | a single flat table for Excel |

Two decisions worth explaining, because they are the ones that make the numbers
mean something:

- **`bi_hechos_dias` exists because counting events lies.** A two-week festival
  and a one-hour talk are both "one event", so a plain count says the workload
  is identical. Exploding each event into the days it occupies is what lets you
  ask which weeks are actually crowded and how many things run at once.
- **Attendance is `NULL`, never `0`, when nobody reported it.** The underlying
  column accepts a number, `N/A`, `Pending` or a short status note. Only real
  numbers are cast; the rest stays empty so averages are not dragged down by
  events that were simply never measured. The `% attendance reported` measure
  turns that gap into its own indicator: when it drops, any conclusion about
  audience is fragile, and the report should say so.

## Connecting Power BI

1. **Get data › MySQL database.** Server `127.0.0.1`, database `calendario_demo`.
   Against a remote server, tunnel over SSH first and point Power BI at the
   local end of the tunnel rather than exposing port 3306.
2. Select the `bi_` views only. Load.
3. Create the relationships listed at the top of `docs/bi/medidas.dax`. All of
   them are single-direction except the origins bridge, which must be
   **both directions** because it is a many-to-many.
4. Mark `bi_dim_fecha` as the date table.
5. Paste the measures from `docs/bi/medidas.dax`.

No MySQL connector? Export flat files instead:

```bash
php bin/exportar_bi.php          # writes storage/bi/*.csv
```

One CSV per view, UTF-8 with BOM so Excel on Windows reads the accents. The
folder is gitignored: it is generated data, not source.

## Suggested report pages

- **Overview.** Events, completion rate, attendance, occupied days, and a
  monthly trend line with the previous-month variation.
- **By department.** Events, average duration, share concentrated in the peak
  month. This is the page that shows whether a department spreads its year or
  crams it.
- **Calendar pressure.** A matrix of week by department built on
  `bi_hechos_dias`, plus the maximum number of concurrent events.
- **Data quality.** Percentage of events with attendance, evidence and
  partners reported. Useful on its own, and it keeps the other pages honest.
