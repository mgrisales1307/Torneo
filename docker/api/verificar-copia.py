"""Compara todos los campos de SQLite con PostgreSQL en un contenedor de prueba."""

import argparse
import json
import sqlite3
import subprocess


def comparar(origen, contenedor, esquema):
    sqlite = sqlite3.connect('file:' + origen + '?mode=ro', uri=True)
    sqlite.row_factory = sqlite3.Row
    tablas = sqlite.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name").fetchall()
    total = 0
    for tabla in tablas:
        nombre = tabla[0]
        columnas = sqlite.execute('PRAGMA table_info("' + nombre.replace('"', '""') + '")').fetchall()
        consulta = 'SELECT coalesce(json_agg(t), ' + "'[]'" + ') FROM (SELECT * FROM "' + esquema.replace('"', '""') + '"."' + nombre.replace('"', '""') + '") t'
        respuesta = subprocess.check_output(['docker', 'exec', contenedor, 'psql', '-U', 'postgres', '-tA', '-c', consulta], text=True)
        postgres = json.loads(respuesta)
        originales = [dict(r) for r in sqlite.execute('SELECT * FROM "' + nombre.replace('"', '""') + '"')]
        for fila in postgres:
            for columna in columnas:
                clave, tipo = columna[1], columna[2].lower()
                if fila[clave] is None:
                    continue
                if tipo == 'tinyint(1)':
                    fila[clave] = int(fila[clave])
                elif tipo in ('datetime', 'timestamp'):
                    fila[clave] = fila[clave].replace('T', ' ')
        canonico = lambda fila: json.dumps(fila, sort_keys=True, ensure_ascii=False)
        if sorted(map(canonico, originales)) != sorted(map(canonico, postgres)):
            raise ValueError('Los campos no coinciden en la tabla ' + nombre)
        total += len(originales)
        print(nombre + ': ' + str(len(originales)) + ' registros idénticos')
    print(f'Verificadas {len(tablas)} tablas y {total} registros, campo por campo.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('origen')
    parser.add_argument('contenedor')
    parser.add_argument('--esquema', default='nexzone')
    opciones = parser.parse_args()
    comparar(opciones.origen, opciones.contenedor, opciones.esquema)
