<?php

namespace Modules\Crm\Models;

use App\Core\Database;

/** أشخاص الجهة: تابعون للجهة نفسها فيراهم كل من يصل إليها من أي مساحة. */
class Contact
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM crm_contacts WHERE id = :id', ['id' => $id]);
    }

    public static function forOrganization(int $organizationId): array
    {
        return Database::select(
            "SELECT * FROM crm_contacts WHERE organization_id = :o ORDER BY status = 'inactive', name",
            ['o' => $organizationId]
        );
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('crm_contacts', $data);
    }

    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('crm_contacts', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('crm_contacts', 'id = :id', ['id' => $id]);
    }
}
