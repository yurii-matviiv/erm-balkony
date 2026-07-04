<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time data cleanup: the old CRM logged supplier phone calls as rows in
 * `clients` with caller_type='supplier' (because there was no proper place
 * to put them at the time). Those rows got synced into our new `clients`
 * table as-is by ClientsSyncMapper, which is wrong long-term — suppliers
 * are not a type of client (see Supplier model docblock). This command
 * moves them to where they belong: a SupplierContact under the correct
 * Supplier, then deletes the now-redundant Client row.
 *
 * This is NOT a repeatable sync mapper. The matching below (which old
 * client row belongs to which supplier company) required reading every
 * row's free-text name/comment by hand, because the old data has no
 * foreign key linking a call to a company — the company name is just
 * embedded in the caller's name (e.g. "Водій Алнат" = "Alnat's driver").
 * It only ever needs to run once, which is why the mapping is a plain
 * hardcoded array here instead of pattern-matching logic that could
 * mis-assign a contact to the wrong supplier.
 *
 * A handful of rows (no identifiable company, e.g. "будівна компанія",
 * "Тетяна Галіон") were deliberately left out of both lists below — they
 * stay untouched in `clients` until someone confirms who they actually
 * are. One row (id 11538, "Вакансія монтажника" — a job posting, not a
 * contact) is hard-deleted outright, see $junkClientIds.
 */
class MoveSupplierContactsFromClients extends Command
{
    protected $signature = 'erm:move-supplier-contacts-from-clients {--dry-run}';

    protected $description = 'One-time move of clients.caller_type=supplier rows into supplier_contacts, then delete the originals';

    /**
     * Client rows that clearly belong to an EXISTING supplier (matched by
     * reading the embedded company name in the client's "name" field).
     * Keyed by new suppliers.id => list of old/new clients.id.
     *
     * Supplier ids, for reference (from `suppliers` table at the time this
     * was written): 2=Фрам-Лайн, 4=Валько, 8=Алнат, 11=ТОВ "КОМ-ФОРТ",
     * 12=EKIPAZH, 15="ІНТЕР-НОРМ"-Віконний Стандарт, 18=Микола по вивозу
     * сміття, 19=Послуги відкоси, 21=Вікно Україна.
     *
     * @var array<int, array<int, int>>
     */
    private array $existingSupplierMatches = [
        8 => [3113, 8078, 8510], // Алнат
        21 => [8077, 8095, 8231], // Вікно Україна
        12 => [8101, 8203, 8207, 8462, 8976, 9260, 9337, 9658, 9909, 11681], // EKIPAZH
        4 => [8297], // Валько
        2 => [8305, 8562, 8582, 8635, 8687, 8842, 8892, 9602, 9924, 10075, 10597], // Фрам-Лайн
        15 => [8419], // "ІНТЕР-НОРМ" - Віконний Стандарт
        11 => [8471], // ТОВ "КОМ-ФОРТ"
        19 => [9172, 12016], // Послуги відкоси
        18 => [8840, 9363], // Микола по вивозу сміття
    ];

    /**
     * Client rows whose company is NOT yet in `suppliers` at all — a new
     * Supplier is created for each group (named by the array key), then
     * every listed client becomes a contact under it.
     *
     * @var array<string, array<int, int>>
     */
    private array $newSupplierGroups = [
        'Werzalit' => [3350],
        'Вікналенд' => [8165],
        'Rehau' => [8718],
        'Лідер' => [8961],
        'ТОВ ВКФ 2Д' => [8994, 9241],
        'ТОВ ЕНЕРГО МОНТАЖ СЕРВІС' => [9052],
        'Віконда' => [9085],
        'Інтерметалпласт' => [9108],
        'БізнесЛейбл' => [9264],
        'Ролетні системи ВСГруп' => [9503],
        'Гудвін' => [11117, 11428],
    ];

    /**
     * Not a contact at all (a job vacancy posting that got logged as a
     * "client" call) — deleted outright, not moved anywhere.
     *
     * @var array<int, int>
     */
    private array $junkClientIds = [11538];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $movedContacts = 0;
        $createdSuppliers = 0;
        $deletedClients = 0;

        DB::transaction(function () use ($dryRun, &$movedContacts, &$createdSuppliers, &$deletedClients) {
            foreach ($this->existingSupplierMatches as $supplierId => $clientIds) {
                foreach ($clientIds as $clientId) {
                    $client = Client::find($clientId);

                    if (! $client) {
                        $this->warn("Клієнт #{$clientId} не знайдений — пропускаю.");

                        continue;
                    }

                    $this->line("Постачальник #{$supplierId}: контакт «{$client->full_name}» ({$client->phone})");

                    if (! $dryRun) {
                        $this->createContactFromClient($supplierId, $client);
                        $client->delete();
                    }

                    $movedContacts++;
                    $deletedClients++;
                }
            }

            foreach ($this->newSupplierGroups as $supplierName => $clientIds) {
                $this->line("Новий постачальник «{$supplierName}»:");

                $supplierId = null;

                if (! $dryRun) {
                    $supplier = Supplier::create([
                        'name' => $supplierName,
                        'notes' => 'Створено автоматично під час перенесення контактів зі старої таблиці clients (тип: постачальник).',
                    ]);
                    $supplierId = $supplier->id;
                }

                $createdSuppliers++;

                foreach ($clientIds as $clientId) {
                    $client = Client::find($clientId);

                    if (! $client) {
                        $this->warn("  Клієнт #{$clientId} не знайдений — пропускаю.");

                        continue;
                    }

                    $this->line("  контакт «{$client->full_name}» ({$client->phone})");

                    if (! $dryRun) {
                        $this->createContactFromClient($supplierId, $client);
                        $client->delete();
                    }

                    $movedContacts++;
                    $deletedClients++;
                }
            }

            foreach ($this->junkClientIds as $clientId) {
                $client = Client::find($clientId);

                if (! $client) {
                    continue;
                }

                $this->line("Видаляю сміттєвий запис: «{$client->full_name}» ({$client->phone})");

                if (! $dryRun) {
                    $client->delete();
                }

                $deletedClients++;
            }
        });

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '').
            "Контактів перенесено: {$movedContacts}. ".
            "Нових постачальників створено: {$createdSuppliers}. ".
            "Видалено записів з clients: {$deletedClients}."
        );

        return self::SUCCESS;
    }

    private function createContactFromClient(?int $supplierId, Client $client): void
    {
        SupplierContact::create([
            'supplier_id' => $supplierId,
            // Kept exactly as it was in the old system (e.g. "Водій Алнат")
            // rather than split into name/role — safer than guessing at a
            // parsing rule across 47 differently-formatted rows. Easy to
            // tidy up by hand later in the Suppliers admin page.
            'name' => $client->full_name,
            'phone' => $client->phone,
            'email' => $client->email,
            'comment' => 'Перенесено зі старої таблиці клієнтів (тип: постачальник), запис #'.$client->id.'.',
        ]);
    }
}
