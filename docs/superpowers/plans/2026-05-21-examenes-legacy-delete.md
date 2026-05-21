# Examenes Legacy Module Deletion Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete `modules/examenes/` by routing all traffic to the existing Laravel `ExamenesParityController` and eliminating the three cross-module class dependencies.

**Architecture:** All `/examenes/*` and `/imagenes/*` HTTP traffic is already handled by `ExamenesParityController` (2765 lines, real implementations). The bridge in `public/index.php` must be updated to route those prefixes to Laravel. Three legacy modules hold class-level imports into `modules/examenes/`: `MailboxController` (uses `ExamenCrmService` + `ExamenMailLogService`), `Paciente360Service` (uses `ExamenModel`), and `CronRunner` (uses `ExamenesReminderService`). Each dependency is resolved before deletion.

**Tech Stack:** PHP 8.x legacy routing (`modules/`), Laravel 12 (parity controller + routes), PDO direct queries.

---

## File Map

| Action | File |
|--------|------|
| Modify | `public/index.php` |
| Create | `models/ExamenModel.php` (copy from module) |
| Modify | `modules/Mail/Controllers/MailboxController.php` |
| Create | `modules/CronManager/Services/ExamenesReminderService.php` (copy+renamespace) |
| Modify | `modules/CronManager/Services/CronRunner.php` |
| Delete | `modules/examenes/` (entire directory) |

---

### Task 1: Add /examenes and /imagenes to the legacy bridge

**Why:** `public/index.php` only forwards `/v2/*` (and a few exact paths) to Laravel. All `/examenes/*` and `/imagenes/*` requests currently reach the legacy `modules/examenes/routes.php`. Adding these as `$laravelBridgePrefixes` makes every request for those paths go to `v2_kernel.php` → Laravel → `ExamenesParityController` instead.

**Files:**
- Modify: `public/index.php`

- [ ] **Step 1: Read the file and locate the two bridge arrays**

```bash
grep -n "laravelBridgeExact\|laravelBridgePrefixes" /path/to/MedForge/public/index.php
```

Expected output shows lines 9–10 (adjust if different):
```
9:$laravelBridgeExact = [...];
10:$laravelBridgePrefixes = ['/v2', '/usuarios', '/roles', '/feedback', '/protocolos'];
```

- [ ] **Step 2: Add the new prefixes**

Find this exact line in `public/index.php`:
```php
$laravelBridgePrefixes = ['/v2', '/usuarios', '/roles', '/feedback', '/protocolos'];
```
Replace with:
```php
$laravelBridgePrefixes = ['/v2', '/usuarios', '/roles', '/feedback', '/protocolos', '/examenes', '/imagenes'];
```

- [ ] **Step 3: Verify the change looks correct**

```bash
grep "laravelBridgePrefixes" public/index.php
```

Expected:
```
$laravelBridgePrefixes = ['/v2', '/usuarios', '/roles', '/feedback', '/protocolos', '/examenes', '/imagenes'];
```

- [ ] **Step 4: Smoke-test that the legacy module still loads**

Confirm `modules/examenes/routes.php` still parses without PHP errors (it will stop being called once the bridge is active, but should not have syntax errors):
```bash
php -l modules/examenes/routes.php
```
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add public/index.php
git commit -m "feat(examenes): route /examenes and /imagenes prefixes to Laravel bridge"
```

---

### Task 2: Copy ExamenModel to root models/

**Why:** `modules/Pacientes/Services/Paciente360Service.php` imports `Modules\Examenes\Models\ExamenModel`. When `modules/examenes/` is deleted that class disappears. Copying the model file to `models/ExamenModel.php` (keeping the identical namespace) preserves all existing `use` statements in every consumer without any edits. This is the same strategy used for `SolicitudModel` in the previous migration.

**Files:**
- Create: `models/ExamenModel.php`

- [ ] **Step 1: Verify the source file**

```bash
head -5 modules/examenes/models/ExamenModel.php
```

Expected first lines:
```php
<?php

namespace Modules\Examenes\Models;
```

- [ ] **Step 2: Copy the file**

```bash
cp modules/examenes/models/ExamenModel.php models/ExamenModel.php
```

- [ ] **Step 3: Verify the copy is identical and syntactically valid**

```bash
diff modules/examenes/models/ExamenModel.php models/ExamenModel.php
php -l models/ExamenModel.php
```

Expected: no diff output, `No syntax errors detected`

- [ ] **Step 4: Confirm Paciente360Service still resolves (static check)**

```bash
grep "ExamenModel" modules/Pacientes/Services/Paciente360Service.php
# Must still show: use Modules\Examenes\Models\ExamenModel;
# After deletion, models/ExamenModel.php provides that class via autoloader
```

- [ ] **Step 5: Commit**

```bash
git add models/ExamenModel.php
git commit -m "feat(examenes): copy ExamenModel to root models/ to decouple Paciente360Service"
```

---

### Task 3: Inline ExamenCrmService calls in MailboxController

**Why:** `MailboxController` uses `ExamenCrmService` for two operations: `registrarNota()` (INSERT into `examen_crm_notas`) and `obtenerContactoPaciente()` (SELECT joining `consulta_examenes` + `patient_data` + `examen_crm_detalles`). Replacing both with direct PDO removes the class dependency. The WhatsApp notification that `registrarNota` triggered internally is intentionally dropped here — that path now goes through the Laravel CRM routes directly.

**Files:**
- Modify: `modules/Mail/Controllers/MailboxController.php`

- [ ] **Step 1: Read the top of MailboxController to see current imports and constructor**

```bash
sed -n '1,40p' modules/Mail/Controllers/MailboxController.php
```

You will see:
```php
use Modules\Examenes\Services\ExamenMailLogService;
use Modules\Examenes\Services\ExamenCrmService;
...
private ExamenCrmService $examenCrm;
private ExamenMailLogService $examenMailLog;
...
$this->examenCrm = new ExamenCrmService($pdo);
$this->examenMailLog = new ExamenMailLogService($pdo);
```

- [ ] **Step 2: Remove ExamenCrmService import**

Find:
```php
use Modules\Examenes\Services\ExamenCrmService;
```
Delete that line entirely.

- [ ] **Step 3: Remove examenCrm property declaration**

Find:
```php
    private ExamenCrmService $examenCrm;
```
Delete that line.

- [ ] **Step 4: Remove examenCrm constructor init**

Find:
```php
        $this->examenCrm = new ExamenCrmService($pdo);
```
Delete that line.

- [ ] **Step 5: Locate the 'examen' case that calls examenCrm**

```bash
grep -n "examenCrm" modules/Mail/Controllers/MailboxController.php
```

You will see two lines: `registrarNota` and `obtenerContactoPaciente`. Note their exact line numbers.

- [ ] **Step 6: Replace the examenCrm->registrarNota call**

Find this exact block (the `case 'examen':` section):
```php
                case 'examen':
                    $this->examenCrm->registrarNota($targetId, $message, $this->getCurrentUserId());
                    $link = '/examenes/' . $targetId . '/crm';
                    $emailContext = $this->examenCrm->obtenerContactoPaciente($targetId);
                    break;
```

Replace with:
```php
                case 'examen':
                    $notaTexto = trim(strip_tags($message));
                    if ($notaTexto !== '') {
                        $stmtNota = $this->pdo->prepare(
                            'INSERT INTO examen_crm_notas (examen_id, autor_id, nota) VALUES (:examen_id, :autor_id, :nota)'
                        );
                        $stmtNota->bindValue(':examen_id', $targetId, \PDO::PARAM_INT);
                        $stmtNota->bindValue(':autor_id', $this->getCurrentUserId(), \PDO::PARAM_INT);
                        $stmtNota->bindValue(':nota', $notaTexto, \PDO::PARAM_STR);
                        $stmtNota->execute();
                    }
                    $link = '/examenes/' . $targetId . '/crm';
                    $stmtCtxEx = $this->pdo->prepare(
                        "SELECT
                            CONCAT(TRIM(pd.fname), ' ', TRIM(pd.mname), ' ', TRIM(pd.lname), ' ', TRIM(pd.lname2)) AS name,
                            detalles.contacto_email AS email,
                            ce.hc_number,
                            ce.form_id,
                            ce.examen_nombre AS descripcion
                         FROM consulta_examenes ce
                         INNER JOIN patient_data pd ON ce.hc_number = pd.hc_number
                         LEFT JOIN examen_crm_detalles detalles ON detalles.examen_id = ce.id
                         WHERE ce.id = ?
                         LIMIT 1"
                    );
                    $stmtCtxEx->execute([$targetId]);
                    $rowEx = $stmtCtxEx->fetch(\PDO::FETCH_ASSOC);
                    if ($rowEx !== false && $rowEx !== null) {
                        $emailContext = array_filter($rowEx, static fn($v) => $v !== null && $v !== '');
                        if ($emailContext === []) {
                            $emailContext = null;
                        }
                    }
                    break;
```

- [ ] **Step 7: Verify no remaining examenCrm references**

```bash
grep "examenCrm" modules/Mail/Controllers/MailboxController.php
```

Expected: no output.

- [ ] **Step 8: Syntax check**

```bash
php -l modules/Mail/Controllers/MailboxController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 9: Commit**

```bash
git add modules/Mail/Controllers/MailboxController.php
git commit -m "refactor(mail): inline ExamenCrmService calls in MailboxController, drop class dependency"
```

---

### Task 4: Inline ExamenMailLogService calls in MailboxController

**Why:** MailboxController also depends on `ExamenMailLogService` for a single `create()` call that inserts a row into `examen_mail_log`. The INSERT is straightforward — direct PDO removes the remaining dependency on the examenes module from MailboxController.

**Files:**
- Modify: `modules/Mail/Controllers/MailboxController.php`

- [ ] **Step 1: Remove ExamenMailLogService import**

Find:
```php
use Modules\Examenes\Services\ExamenMailLogService;
```
Delete that line.

- [ ] **Step 2: Remove examenMailLog property declaration**

Find:
```php
    private ExamenMailLogService $examenMailLog;
```
Delete that line.

- [ ] **Step 3: Remove examenMailLog constructor init**

Find:
```php
        $this->examenMailLog = new ExamenMailLogService($pdo);
```
Delete that line.

- [ ] **Step 4: Locate examenMailLog->create() call**

```bash
grep -n "examenMailLog" modules/Mail/Controllers/MailboxController.php
```

Note the exact line. You will see something like:
```php
            $this->examenMailLog->create([
                'examen_id' => $examenId,
                ...
            ]);
```

- [ ] **Step 5: Replace with inline PDO INSERT**

Find the entire `$this->examenMailLog->create([...])` block (it spans several lines). Replace it with:
```php
            $stmtLog = $this->pdo->prepare(
                "INSERT INTO examen_mail_log
                    (examen_id, form_id, hc_number, to_emails, cc_emails, subject, body_text, body_html, channel, sent_by_user_id, status, error_message, sent_at)
                 VALUES
                    (:examen_id, :form_id, :hc_number, :to_emails, :cc_emails, :subject, :body_text, :body_html, :channel, :sent_by_user_id, :status, :error_message, :sent_at)"
            );
            $bindNullableStr = static function (\PDOStatement $s, string $k, mixed $v): void {
                $s->bindValue($k, ($v !== null && $v !== '') ? (string) $v : null, ($v !== null && $v !== '') ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
            };
            $bindNullableInt = static function (\PDOStatement $s, string $k, mixed $v): void {
                $s->bindValue($k, $v !== null ? (int) $v : null, $v !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
            };
            $bindNullableInt($stmtLog, ':examen_id', $notification['examen_id'] ?? ($examenId ?? null));
            $bindNullableStr($stmtLog, ':form_id', $context['form_id'] ?? null);
            $bindNullableStr($stmtLog, ':hc_number', $context['hc_number'] ?? null);
            $stmtLog->bindValue(':to_emails', $toEmail, \PDO::PARAM_STR);
            $bindNullableStr($stmtLog, ':cc_emails', null);
            $stmtLog->bindValue(':subject', $notification['subject'] ?? ('Actualización de Examen #' . ($examenId ?? 0)), \PDO::PARAM_STR);
            $bindNullableStr($stmtLog, ':body_text', $notification['body_text'] ?? null);
            $bindNullableStr($stmtLog, ':body_html', null);
            $stmtLog->bindValue(':channel', $notification['channel'] ?? 'email', \PDO::PARAM_STR);
            $bindNullableInt($stmtLog, ':sent_by_user_id', $this->getCurrentUserId());
            $stmtLog->bindValue(':status', $notification['status'] ?? 'failed', \PDO::PARAM_STR);
            $bindNullableStr($stmtLog, ':error_message', $notification['error'] ?? null);
            $bindNullableStr($stmtLog, ':sent_at', $notification['sent_at'] ?? null);
            $stmtLog->execute();
```

**Note:** Match the variable names (`$notification`, `$examenId`, `$context`, `$toEmail`) to whatever MailboxController uses in the surrounding method. Read the surrounding 20 lines before making this edit to ensure variable names match exactly.

- [ ] **Step 6: Verify no remaining examenMailLog or ExamenMailLogService references**

```bash
grep "examenMailLog\|ExamenMailLogService" modules/Mail/Controllers/MailboxController.php
```

Expected: no output.

- [ ] **Step 7: Verify no remaining Modules\Examenes imports**

```bash
grep "Modules\\\\Examenes" modules/Mail/Controllers/MailboxController.php
```

Expected: no output.

- [ ] **Step 8: Syntax check**

```bash
php -l modules/Mail/Controllers/MailboxController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 9: Commit**

```bash
git add modules/Mail/Controllers/MailboxController.php
git commit -m "refactor(mail): inline ExamenMailLogService in MailboxController, drop class dependency"
```

---

### Task 5: Relocate ExamenesReminderService to CronManager

**Why:** `CronRunner` (`modules/CronManager/Services/CronRunner.php`) calls `ExamenesReminderService->dispatchUpcoming(72, 48)` on a schedule to fire Pusher notifications for upcoming exams. The service lives in `modules/examenes/services/` and cannot stay there after deletion. The cleanest minimal approach is to copy it into `modules/CronManager/Services/` (with updated namespace) so `CronRunner` can use it independently of the examenes module. A future migration will move this to a Laravel Artisan command when `CronManager` itself is migrated.

**Files:**
- Create: `modules/CronManager/Services/ExamenesReminderService.php`
- Modify: `modules/CronManager/Services/CronRunner.php`

- [ ] **Step 1: Read the source file namespace**

```bash
head -5 modules/examenes/services/ExamenesReminderService.php
```

Expected:
```php
<?php

namespace Modules\Examenes\Services;
```

- [ ] **Step 2: Copy the file**

```bash
cp modules/examenes/services/ExamenesReminderService.php modules/CronManager/Services/ExamenesReminderService.php
```

- [ ] **Step 3: Update the namespace in the copy**

In `modules/CronManager/Services/ExamenesReminderService.php`, find:
```php
namespace Modules\Examenes\Services;
```
Replace with:
```php
namespace Modules\CronManager\Services;
```

- [ ] **Step 4: Syntax check the copy**

```bash
php -l modules/CronManager/Services/ExamenesReminderService.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Update CronRunner import**

In `modules/CronManager/Services/CronRunner.php`, find:
```php
use Modules\Examenes\Services\ExamenesReminderService;
```
Replace with:
```php
use Modules\CronManager\Services\ExamenesReminderService;
```

- [ ] **Step 6: Syntax check CronRunner**

```bash
php -l modules/CronManager/Services/CronRunner.php
```

Expected: `No syntax errors detected`

- [ ] **Step 7: Verify no remaining Modules\Examenes references in CronRunner**

```bash
grep "Modules\\\\Examenes" modules/CronManager/Services/CronRunner.php
```

Expected: no output.

- [ ] **Step 8: Commit**

```bash
git add modules/CronManager/Services/ExamenesReminderService.php modules/CronManager/Services/CronRunner.php
git commit -m "refactor(cron): relocate ExamenesReminderService to CronManager to decouple from examenes module"
```

---

### Task 6: Verify zero remaining cross-dependencies and delete modules/examenes/

**Why:** This is the deletion step. Before running `rm -rf`, do a final grep for any remaining class-level `use` statements pointing into `modules/examenes/`. The check covers only `modules/` and root-level files — Laravel-side references to `Modules\Examenes\*` classes are in `laravel-app/` and are expected to remain (they're the destination, not the source).

**Files:**
- Delete: `modules/examenes/` (entire directory)

- [ ] **Step 1: Final dependency scan — zero tolerance**

```bash
grep -rn "Modules\\\\Examenes" /path/to/MedForge/modules/ --include="*.php" \
  | grep -v "^/path/to/MedForge/modules/examenes/"
```

Expected: **zero lines**. If any lines appear, do NOT proceed — fix each dependency first.

- [ ] **Step 2: Check root models/ already has ExamenModel**

```bash
ls models/ExamenModel.php
php -l models/ExamenModel.php
```

Expected: file exists, `No syntax errors detected`.

- [ ] **Step 3: Confirm ExamenesReminderService exists in CronManager**

```bash
ls modules/CronManager/Services/ExamenesReminderService.php
grep "namespace" modules/CronManager/Services/ExamenesReminderService.php
```

Expected: file exists, namespace is `Modules\CronManager\Services`.

- [ ] **Step 4: Confirm CronRunner no longer imports from Examenes**

```bash
grep "Modules\\\\Examenes" modules/CronManager/Services/CronRunner.php
```

Expected: no output.

- [ ] **Step 5: Confirm MailboxController no longer imports from Examenes**

```bash
grep "Modules\\\\Examenes" modules/Mail/Controllers/MailboxController.php
```

Expected: no output.

- [ ] **Step 6: Delete the module directory**

```bash
rm -rf modules/examenes/
```

- [ ] **Step 7: Stage and commit the deletion**

```bash
git add -u
git status
# Confirm only deletions from modules/examenes/ appear
git commit -m "feat(examenes): delete legacy modules/examenes/ — all traffic now served by ExamenesParityController"
```

- [ ] **Step 8: Final syntax check on the three modified files**

```bash
php -l modules/Mail/Controllers/MailboxController.php
php -l modules/CronManager/Services/CronRunner.php
php -l modules/Pacientes/Services/Paciente360Service.php
```

All three must show `No syntax errors detected`.

- [ ] **Step 9: Smoke-test the active routes (manual)**

Hit the following URLs in a browser or with curl (adjust host):
- `GET /examenes` → should redirect to `/v2/examenes`
- `POST /examenes/kanban-data` → should return JSON (handled by `ExamenesParityController::kanbanData`)
- `GET /imagenes/dashboard` → should redirect to `/v2/imagenes/dashboard`

If all return 200/302 (no 500), migration is complete.
