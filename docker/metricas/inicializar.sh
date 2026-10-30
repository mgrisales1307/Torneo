#!/bin/sh
set -eu
# psql cita los valores como literales; no interpolar contraseñas en SQL.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname metabase \
  --set=escritor="$METRICAS_ESCRITOR_PASSWORD" --set=lector="$METRICAS_LECTOR_PASSWORD" <<'SQL'
CREATE ROLE metricas_escritor LOGIN PASSWORD :'escritor';
CREATE ROLE metricas_lector LOGIN PASSWORD :'lector';
CREATE DATABASE metricas OWNER metricas_escritor;
REVOKE ALL ON DATABASE metabase FROM PUBLIC;
REVOKE ALL ON DATABASE metricas FROM PUBLIC;
GRANT CONNECT ON DATABASE metricas TO metricas_escritor, metricas_lector;
\connect metricas
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO metricas_escritor;
GRANT USAGE ON SCHEMA public TO metricas_lector;
SET ROLE metricas_escritor;
CREATE TABLE trafico_diario (
    fecha date NOT NULL, pagina varchar(40) NOT NULL, dispositivo varchar(12) NOT NULL,
    visitas integer NOT NULL, sesiones integer NOT NULL,
    PRIMARY KEY (fecha, pagina, dispositivo)
);
CREATE TABLE sesiones_diarias (fecha date PRIMARY KEY, sesiones integer NOT NULL);
CREATE TABLE rendimiento_diario (
    fecha date NOT NULL, metrica varchar(8) NOT NULL, p75 double precision NOT NULL,
    muestras integer NOT NULL, PRIMARY KEY (fecha, metrica)
);
CREATE TABLE actividad_diaria (
    fecha date PRIMARY KEY, registros integer NOT NULL DEFAULT 0,
    inscripciones integer NOT NULL DEFAULT 0, pagos_virtuales_cop numeric(18,2) NOT NULL DEFAULT 0
);
CREATE TABLE torneos_por_juego (
    juego varchar(20) NOT NULL, estado varchar(30) NOT NULL,
    torneos integer NOT NULL, inscripciones integer NOT NULL, confirmadas integer NOT NULL,
    cupos integer NOT NULL, PRIMARY KEY (juego, estado)
);
CREATE TABLE inscripciones_por_estado (estado varchar(30) PRIMARY KEY, cantidad integer NOT NULL);
CREATE TABLE resumen_plataforma (
    id integer PRIMARY KEY CHECK (id = 1), jugadores integer NOT NULL, equipos integer NOT NULL,
    torneos integer NOT NULL, confirmadas integer NOT NULL, pagos_virtuales_cop numeric(18,2) NOT NULL,
    actualizado_en timestamp with time zone NOT NULL
);
GRANT SELECT ON ALL TABLES IN SCHEMA public TO metricas_lector;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO metricas_lector;
SQL
