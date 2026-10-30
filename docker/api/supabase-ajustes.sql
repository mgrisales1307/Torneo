-- Ajustes de infraestructura; no modifica registros del proyecto.
REVOKE EXECUTE ON FUNCTION public.rls_auto_enable() FROM PUBLIC, anon, authenticated;

-- PostgreSQL no crea índices para las claves foráneas automáticamente.
DO $$
DECLARE clave record;
BEGIN
    FOR clave IN
        SELECT c.conrelid, c.conname, n.nspname, t.relname,
               string_agg(format('%I', a.attname), ', ' ORDER BY k.orden) AS columnas
        FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        CROSS JOIN LATERAL unnest(c.conkey) WITH ORDINALITY k(numero, orden)
        JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.numero
        WHERE c.contype = 'f' AND n.nspname = 'nexzone'
          AND NOT EXISTS (
              SELECT 1 FROM pg_index i
              WHERE i.indrelid = c.conrelid AND i.indisvalid
                AND i.indpred IS NULL
                AND (i.indkey::smallint[])[0:cardinality(c.conkey)-1] @> c.conkey
          )
        GROUP BY c.conrelid, c.conname, n.nspname, t.relname
    LOOP
        EXECUTE format('CREATE INDEX IF NOT EXISTS %I ON %I.%I (%s)',
                       clave.conname || '_indice', clave.nspname, clave.relname, clave.columnas);
    END LOOP;
END $$;
