<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * History is immutable by construction in the domain; these triggers make the database enforce it too,
 * so neither a bug nor a manual query can rewrite or remove a stored event.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION event_store_append_only() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'event_store is append-only: % is not allowed', TG_OP;
            END;
            $$;

            CREATE TRIGGER event_store_no_update_or_delete
                BEFORE UPDATE OR DELETE ON event_store
                FOR EACH ROW EXECUTE FUNCTION event_store_append_only();

            CREATE TRIGGER event_store_no_truncate
                BEFORE TRUNCATE ON event_store
                FOR EACH STATEMENT EXECUTE FUNCTION event_store_append_only();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS event_store_no_truncate ON event_store;
            DROP TRIGGER IF EXISTS event_store_no_update_or_delete ON event_store;
            DROP FUNCTION IF EXISTS event_store_append_only();
            SQL);
    }
};
