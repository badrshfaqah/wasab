<?php

namespace Modules\Documents\Models;

use App\Core\Database;

class DocumentSetting
{
    /** صف إعدادات واحد فقط لكل شركة - يُنشأ تلقائياً بأول استخدام. */
    public static function getOrCreate(int $companyId): array
    {
        $row = Database::first('SELECT * FROM documents_settings WHERE company_id = :c', ['c' => $companyId]);
        if ($row) {
            return $row;
        }

        Database::insert('documents_settings', [
            'company_id' => $companyId,
            'number_prefix' => 'DOC',
            'last_sequence' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Database::first('SELECT * FROM documents_settings WHERE company_id = :c', ['c' => $companyId]);
    }

    public static function update(int $companyId, array $data): void
    {
        self::getOrCreate($companyId);
        $data['updated_at'] = date('Y-m-d H:i:s');
        Database::update('documents_settings', $data, 'company_id = :c', ['c' => $companyId]);
    }

    /**
     * يولّد رقم مستند بصيغة PREFIX-YEAR-000 بتسلسل تصاعدي دائم لكل شركة، بمعزل عن قفل صفوف
     * (SELECT ... FOR UPDATE) داخل معاملة لتفادي تكرار نفس الرقم عند طلبين متزامنين.
     */
    public static function generateNumber(int $companyId): string
    {
        self::getOrCreate($companyId);

        $pdo = Database::pdo();
        $inTransaction = $pdo->inTransaction();
        if (!$inTransaction) {
            Database::beginTransaction();
        }

        try {
            $row = Database::first(
                'SELECT * FROM documents_settings WHERE company_id = :c FOR UPDATE',
                ['c' => $companyId]
            );
            $nextSequence = (int) $row['last_sequence'] + 1;

            Database::update(
                'documents_settings',
                ['last_sequence' => $nextSequence, 'updated_at' => date('Y-m-d H:i:s')],
                'company_id = :c',
                ['c' => $companyId]
            );

            if (!$inTransaction) {
                Database::commit();
            }
        } catch (\Throwable $e) {
            if (!$inTransaction) {
                Database::rollBack();
            }
            throw $e;
        }

        $prefix = $row['number_prefix'] ?: 'DOC';
        return sprintf('%s-%s-%03d', $prefix, date('Y'), $nextSequence);
    }
}
