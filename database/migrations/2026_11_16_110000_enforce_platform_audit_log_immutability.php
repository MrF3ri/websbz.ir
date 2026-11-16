<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION prevent_platform_audit_log_mutation()
                RETURNS trigger
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    RAISE EXCEPTION 'Platform audit logs are immutable.'
                        USING ERRCODE = '55000';
                END;
                $$;

                CREATE TRIGGER platform_audit_logs_prevent_update
                BEFORE UPDATE ON platform_audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_platform_audit_log_mutation();

                CREATE TRIGGER platform_audit_logs_prevent_delete
                BEFORE DELETE ON platform_audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_platform_audit_log_mutation();
            SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER platform_audit_logs_prevent_update
                BEFORE UPDATE ON platform_audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Platform audit logs are immutable.');
                END;

                CREATE TRIGGER platform_audit_logs_prevent_delete
                BEFORE DELETE ON platform_audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Platform audit logs are immutable.');
                END;
            SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS platform_audit_logs_prevent_update ON platform_audit_logs;
                DROP TRIGGER IF EXISTS platform_audit_logs_prevent_delete ON platform_audit_logs;
                DROP FUNCTION IF EXISTS prevent_platform_audit_log_mutation();
            SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS platform_audit_logs_prevent_update;
                DROP TRIGGER IF EXISTS platform_audit_logs_prevent_delete;
            SQL);
        }
    }
};
