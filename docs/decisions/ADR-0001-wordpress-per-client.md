# ADR-0001 — WordPress con instalación independiente por cliente

- **Estado:** adoptada para el MVP.
- **Fecha:** 2026-11-12.

## Contexto

Nexora necesita un catálogo reutilizable, un panel administrativo y entrega comprimida para el VPS del negocio. Se busca acelerar el MVP aprovechando herramientas nativas de administración, usuarios y medios, sin Docker.

## Decisión

Utilizar WordPress como runtime; desarrollar un plugin propio `nexora-catalog-core` para datos y lógica, y un tema propio `nexora-catalog-theme` para la presentación. Cada cliente tendrá su propia instalación, base de datos, archivos, dominio y credenciales.

## Consecuencias positivas

- Administración WordPress disponible.
- Usuarios y roles nativos.
- Biblioteca multimedia reutilizable.
- Distribución del plugin y tema como ZIP.
- Aislamiento de clientes por instalación.
- Menos infraestructura propia en el MVP.

## Consecuencias y límites

- Dependencia del ciclo de actualizaciones WordPress/PHP.
- Compatibilidad de plugin y tema debe mantenerse.
- El panel administrativo es WordPress personalizado, no una app separada.
- No existe multi-tenancy ni licencias centralizadas.
- El cliente debe disponer de WordPress funcional en su VPS.

## Alternativas no elegidas en esta etapa

- Laravel a medida: más control, pero requiere construir más infraestructura de administración, usuarios y medios.
- WordPress genérico sin código propio: dependería demasiado de plugins y configuración de terceros.
- SaaS multi-tenant: complejidad innecesaria para la primera entrega.

Revisar esta decisión solo ante requisitos transaccionales complejos, multi-tenancy o integraciones que excedan el catálogo MVP.
