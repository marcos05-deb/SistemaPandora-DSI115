--
-- PostgreSQL database dump
--

\restrict 3dRmqWWrblrinc4WqFrjomOEYo2HvKm1LvHO05qo17e9NE5pi2LWdfcr9c9IN1H

-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


--
-- Name: estado_civil_enum; Type: TYPE; Schema: public; Owner: pandora
--

CREATE TYPE public.estado_civil_enum AS ENUM (
    'Soltero',
    'Casado',
    'Divorciado',
    'Viudo',
    'Unión Libre'
);


ALTER TYPE public.estado_civil_enum OWNER TO pandora;

--
-- Name: parentesco_enum; Type: TYPE; Schema: public; Owner: pandora
--

CREATE TYPE public.parentesco_enum AS ENUM (
    'Padre',
    'Madre',
    'Tutor',
    'Otro'
);


ALTER TYPE public.parentesco_enum OWNER TO pandora;

--
-- Name: sexo_enum; Type: TYPE; Schema: public; Owner: pandora
--

CREATE TYPE public.sexo_enum AS ENUM (
    'M',
    'F',
    'Otro'
);


ALTER TYPE public.sexo_enum OWNER TO pandora;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: areas; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.areas (
    id integer NOT NULL,
    nombre character varying(255) NOT NULL,
    requiere_aprobacion_estricta boolean DEFAULT false NOT NULL
);


ALTER TABLE public.areas OWNER TO pandora;

--
-- Name: areas_id_seq; Type: SEQUENCE; Schema: public; Owner: pandora
--

CREATE SEQUENCE public.areas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.areas_id_seq OWNER TO pandora;

--
-- Name: areas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: pandora
--

ALTER SEQUENCE public.areas_id_seq OWNED BY public.areas.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO pandora;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO pandora;

--
-- Name: carreras; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.carreras (
    id integer NOT NULL,
    facultad_id integer NOT NULL,
    nombre character varying(255) NOT NULL
);


ALTER TABLE public.carreras OWNER TO pandora;

--
-- Name: carreras_id_seq; Type: SEQUENCE; Schema: public; Owner: pandora
--

CREATE SEQUENCE public.carreras_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.carreras_id_seq OWNER TO pandora;

--
-- Name: carreras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: pandora
--

ALTER SEQUENCE public.carreras_id_seq OWNED BY public.carreras.id;


--
-- Name: contactos_paciente; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.contactos_paciente (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    paciente_id uuid NOT NULL,
    nombre_completo character varying(255) NOT NULL,
    parentesco public.parentesco_enum NOT NULL,
    telefono_personal text NOT NULL,
    telefono_casa text,
    direccion text,
    es_responsable boolean DEFAULT false NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(0) with time zone
);


ALTER TABLE public.contactos_paciente OWNER TO pandora;

--
-- Name: expedientes; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.expedientes (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    paciente_id uuid NOT NULL,
    area_id integer NOT NULL,
    motivo_consulta text,
    notas_clinicas text,
    diagnostico text,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(0) with time zone
);


ALTER TABLE public.expedientes OWNER TO pandora;

--
-- Name: facultades; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.facultades (
    id integer NOT NULL,
    nombre character varying(255) NOT NULL
);


ALTER TABLE public.facultades OWNER TO pandora;

--
-- Name: facultades_id_seq; Type: SEQUENCE; Schema: public; Owner: pandora
--

CREATE SEQUENCE public.facultades_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.facultades_id_seq OWNER TO pandora;

--
-- Name: facultades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: pandora
--

ALTER SEQUENCE public.facultades_id_seq OWNED BY public.facultades.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO pandora;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: pandora
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO pandora;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: pandora
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: pacientes; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.pacientes (
    codigo uuid DEFAULT gen_random_uuid() NOT NULL,
    carnet character varying(50) NOT NULL,
    carrera_id integer NOT NULL,
    creado_por_profesional_id uuid NOT NULL,
    sexo public.sexo_enum NOT NULL,
    estado_civil public.estado_civil_enum NOT NULL,
    fecha_nacimiento text NOT NULL,
    profesion_ocupacion character varying(255),
    fecha_primera_consulta text,
    referido_por character varying(255),
    llevado_por character varying(255),
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(0) with time zone,
    nombre_completo text,
    direccion text,
    motivo_consulta text
);


ALTER TABLE public.pacientes OWNER TO pandora;

--
-- Name: permisos; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.permisos (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL
);


ALTER TABLE public.permisos OWNER TO pandora;

--
-- Name: permisos_id_seq; Type: SEQUENCE; Schema: public; Owner: pandora
--

CREATE SEQUENCE public.permisos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permisos_id_seq OWNER TO pandora;

--
-- Name: permisos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: pandora
--

ALTER SEQUENCE public.permisos_id_seq OWNED BY public.permisos.id;


--
-- Name: permission_role; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.permission_role (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.permission_role OWNER TO pandora;

--
-- Name: profesionales; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.profesionales (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id bigint NOT NULL,
    area_id integer NOT NULL,
    especialidad character varying(255) NOT NULL,
    numero_registro character varying(255) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(0) with time zone
);


ALTER TABLE public.profesionales OWNER TO pandora;

--
-- Name: role_user; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.role_user (
    user_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.role_user OWNER TO pandora;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    slug character varying(30),
    nivel smallint DEFAULT '0'::smallint NOT NULL
);


ALTER TABLE public.roles OWNER TO pandora;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: pandora
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO pandora;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: pandora
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO pandora;

--
-- Name: users; Type: TABLE; Schema: public; Owner: pandora
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    kdf_salt text,
    name character varying(255),
    must_change_password boolean DEFAULT true NOT NULL,
    phone character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    failed_login_attempts smallint DEFAULT '0'::smallint NOT NULL,
    locked_until timestamp(0) with time zone,
    deleted_at timestamp(0) with time zone
);


ALTER TABLE public.users OWNER TO pandora;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: pandora
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO pandora;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: pandora
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: areas id; Type: DEFAULT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.areas ALTER COLUMN id SET DEFAULT nextval('public.areas_id_seq'::regclass);


--
-- Name: carreras id; Type: DEFAULT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.carreras ALTER COLUMN id SET DEFAULT nextval('public.carreras_id_seq'::regclass);


--
-- Name: facultades id; Type: DEFAULT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.facultades ALTER COLUMN id SET DEFAULT nextval('public.facultades_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: permisos id; Type: DEFAULT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.permisos ALTER COLUMN id SET DEFAULT nextval('public.permisos_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: areas; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.areas (id, nombre, requiere_aprobacion_estricta) FROM stdin;
1	Psicología	f
2	Medicina General	f
3	Fisioterapia	f
4	Nutrición	f
5	Trabajo Social	f
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.cache (key, value, expiration) FROM stdin;
pandora-cache-3950c659687d31fdea6280c67323b70c432478ea:timer	i:1781148645;	1781148645
pandora-cache-3950c659687d31fdea6280c67323b70c432478ea	i:1;	1781148645
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: carreras; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.carreras (id, facultad_id, nombre) FROM stdin;
1	5	Arquitectura
2	5	Ingenieria Civil
3	5	Ingenieria de Sistemas Informaticos
4	5	Ingenieria Electrica
5	5	Ingenieria Industrial
6	5	Ingenieria Mecanica
7	5	Ingenieria Quimica e Ingenieria de Alimentos
8	5	Unidad de Ciencias Basicas
9	5	Unidad de Planificacion
10	7	Escuela de Ciencias de la Salud
11	7	Escuela de Medicina
12	7	Escuela de Postgrado
13	7	Administracion Academica
14	13	Secretaria de Oficinas Centrales
15	14	Instituto Tecnologico de Costa Rica
16	14	Universidad Nacional de Costa Rica
17	14	Universidad de San Carlos de Guatemala
18	14	Universidad Nacional de Ingenieria de Nicaragua
19	14	Universidad Nacional de Nicaragua
20	14	Universidad Tecnologica Centroamericana
21	14	Universidad Nacional Autonoma de Nicaragua
22	15	General / No especificada
\.


--
-- Data for Name: contactos_paciente; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.contactos_paciente (id, paciente_id, nombre_completo, parentesco, telefono_personal, telefono_casa, direccion, es_responsable, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: expedientes; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.expedientes (id, paciente_id, area_id, motivo_consulta, notas_clinicas, diagnostico, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: facultades; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.facultades (id, nombre) FROM stdin;
1	Facultad de Ciencias Agronomicas
2	Facultad de Ciencias Economicas
3	Facultad de Ciencias Naturales y Matematica
4	Facultad de Ciencias y Humanidades
5	Facultad de Ingenieria y Arquitectura
6	Facultad de Jurisprudencia y Ciencias Sociales
7	Facultad de Medicina
8	Facultad de Odontologia
9	Facultad de Quimica y Farmacia
10	Facultad Multidisciplinaria de Occidente
11	Facultad Multidisciplinaria Oriental
12	Facultad Multidisciplinaria Paracentral
13	Oficinas Centrales
14	Institucion Externa
15	Otra / No especificada
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2024_01_01_000000_setup_postgres_extensions_and_enums	1
2	2024_01_01_000001_create_users_table	1
3	2024_01_01_000002_create_rbac_tables	1
4	2024_01_01_000003_create_institutional_tables	1
5	2024_01_01_000004_create_professionals_table	1
6	2024_01_01_000005_create_patients_tables	1
7	2026_05_31_000001_add_kdf_salt_to_users_table	1
8	2026_05_31_000002_create_sessions_table	1
9	2026_05_31_000003_add_slug_nivel_to_roles_table	1
10	2026_05_31_000004_create_expedientes_table	1
11	2026_05_31_101151_create_cache_table	1
12	2026_05_31_154920_add_name_to_users_table	1
13	2026_05_31_175526_add_must_change_password_to_users_table	1
14	2026_05_31_194450_add_phone_and_is_active_to_users_table	1
15	2026_05_31_202108_modify_columns_for_encryption_in_patients_tables	1
16	2026_05_31_215159_add_nombre_and_direccion_to_pacientes_table	1
17	2026_06_02_000001_add_account_lockout_to_users_table	1
18	2026_06_04_000001_add_deleted_at_to_users_table	1
19	2026_06_04_000002_add_motivo_consulta_to_pacientes_table	1
\.


--
-- Data for Name: pacientes; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.pacientes (codigo, carnet, carrera_id, creado_por_profesional_id, sexo, estado_civil, fecha_nacimiento, profesion_ocupacion, fecha_primera_consulta, referido_por, llevado_por, created_at, updated_at, deleted_at, nombre_completo, direccion, motivo_consulta) FROM stdin;
\.


--
-- Data for Name: permisos; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.permisos (id, nombre) FROM stdin;
\.


--
-- Data for Name: permission_role; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.permission_role (permission_id, role_id) FROM stdin;
\.


--
-- Data for Name: profesionales; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.profesionales (id, user_id, area_id, especialidad, numero_registro, created_at, updated_at, deleted_at) FROM stdin;
019eb4b5-65b9-709e-b5de-6ee7e4f54d2f	2	1	Medicina General	MED-001	2026-06-10 21:24:07+00	2026-06-10 21:24:07+00	\N
019eb4b5-673c-73df-b459-a3dfbcdc5990	3	1	Psicología	PSI-001	2026-06-10 21:24:07+00	2026-06-10 21:24:07+00	\N
019eb4b5-68c6-72b7-952f-2a5482b66418	4	1	Pediatría	PED-001	2026-06-10 21:24:08+00	2026-06-10 21:24:08+00	\N
\.


--
-- Data for Name: role_user; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.role_user (user_id, role_id) FROM stdin;
1	1
2	2
3	3
4	4
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.roles (id, nombre, slug, nivel) FROM stdin;
1	Administrador de Sistema	sysadmin	100
2	Coordinador de Área	area_coordinator	50
3	Referente Psicosocial	psychosocial_referent	20
4	Especialista	specialist	10
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
xTcqLd1b4qkVu34gb72FOshX9JgsYmJosjqzVBdl	\N	172.22.0.1	Mozilla/5.0 (X11; Linux x86_64; rv:151.0) Gecko/20100101 Firefox/151.0	YToyOntzOjY6Il90b2tlbiI7czo0MDoiTzNxWDB4RjgxTjZuY3RFUXBPYW82NUhDVEJBb3dMQkh0aW16M280USI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==	1781152192
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: pandora
--

COPY public.users (id, email, password, remember_token, created_at, updated_at, kdf_salt, name, must_change_password, phone, is_active, failed_login_attempts, locked_until, deleted_at) FROM stdin;
2	coordinador@pandora.com	$argon2id$v=19$m=65536,t=4,p=1$TGlxemVVNm53MmhOOG5XUw$addSFc7x/nzg7D6jg4S+CZJ6SzgiWCkOFhVr66HCP+o	\N	2026-06-10 21:24:07+00	2026-06-10 21:24:07+00	chemlQhC/+4TYiH1sN8n4Q==	Dr. Coordinador	f	\N	t	0	\N	\N
3	psicosocial@pandora.com	$argon2id$v=19$m=65536,t=4,p=1$a25rZFA2UjNhVFkuTFpUaQ$qJ0wMvryiaPQJES+esuElDl5jUeRBKoGL3W02qCKUpE	\N	2026-06-10 21:24:07+00	2026-06-10 21:24:07+00	4rZcgcHQAIcnoTafYEh/8w==	Lic. Psicosocial	f	\N	t	0	\N	\N
4	especialista@pandora.com	$argon2id$v=19$m=65536,t=4,p=1$UEFFalFlaUUzWERKNWVWNw$0J6ji8G2b+9rUDXObCZi72TPAXxIUmc9EQACfM0oobQ	\N	2026-06-10 21:24:08+00	2026-06-10 21:24:08+00	cwI0iZACd2lXIoicHdC/KA==	Dr. Especialista	f	\N	t	0	\N	\N
1	admin@pandora.com	$argon2id$v=19$m=65536,t=4,p=1$ZUV3LjVLbVpLaXlkS3hoMw$DHt7uLBCsouaphhi7Yk5znu4ZOPGjaF5r1v1gSPLXWg	\N	2026-06-10 21:24:07+00	2026-06-10 21:29:46+00	mBmjx08hXWN3YswppxZOjw==	Administrador	f	\N	t	0	\N	\N
\.


--
-- Name: areas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: pandora
--

SELECT pg_catalog.setval('public.areas_id_seq', 5, true);


--
-- Name: carreras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: pandora
--

SELECT pg_catalog.setval('public.carreras_id_seq', 22, true);


--
-- Name: facultades_id_seq; Type: SEQUENCE SET; Schema: public; Owner: pandora
--

SELECT pg_catalog.setval('public.facultades_id_seq', 15, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: pandora
--

SELECT pg_catalog.setval('public.migrations_id_seq', 19, true);


--
-- Name: permisos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: pandora
--

SELECT pg_catalog.setval('public.permisos_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: pandora
--

SELECT pg_catalog.setval('public.roles_id_seq', 4, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: pandora
--

SELECT pg_catalog.setval('public.users_id_seq', 4, true);


--
-- Name: areas areas_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: carreras carreras_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.carreras
    ADD CONSTRAINT carreras_nombre_unique UNIQUE (nombre);


--
-- Name: carreras carreras_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.carreras
    ADD CONSTRAINT carreras_pkey PRIMARY KEY (id);


--
-- Name: contactos_paciente contactos_paciente_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.contactos_paciente
    ADD CONSTRAINT contactos_paciente_pkey PRIMARY KEY (id);


--
-- Name: expedientes expedientes_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.expedientes
    ADD CONSTRAINT expedientes_pkey PRIMARY KEY (id);


--
-- Name: facultades facultades_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.facultades
    ADD CONSTRAINT facultades_nombre_unique UNIQUE (nombre);


--
-- Name: facultades facultades_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.facultades
    ADD CONSTRAINT facultades_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: pacientes pacientes_carnet_unique; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_carnet_unique UNIQUE (carnet);


--
-- Name: pacientes pacientes_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_pkey PRIMARY KEY (codigo);


--
-- Name: permisos permisos_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_nombre_unique UNIQUE (nombre);


--
-- Name: permisos permisos_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_pkey PRIMARY KEY (id);


--
-- Name: permission_role permission_role_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.permission_role
    ADD CONSTRAINT permission_role_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: profesionales profesionales_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.profesionales
    ADD CONSTRAINT profesionales_pkey PRIMARY KEY (id);


--
-- Name: role_user role_user_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.role_user
    ADD CONSTRAINT role_user_pkey PRIMARY KEY (user_id, role_id);


--
-- Name: roles roles_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_nombre_unique UNIQUE (nombre);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_slug_unique; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_slug_unique UNIQUE (slug);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: idx_contactos_deleted_at; Type: INDEX; Schema: public; Owner: pandora
--

CREATE INDEX idx_contactos_deleted_at ON public.contactos_paciente USING btree (deleted_at) WHERE (deleted_at IS NULL);


--
-- Name: idx_contactos_paciente_id; Type: INDEX; Schema: public; Owner: pandora
--

CREATE INDEX idx_contactos_paciente_id ON public.contactos_paciente USING btree (paciente_id);


--
-- Name: idx_expedientes_deleted_at; Type: INDEX; Schema: public; Owner: pandora
--

CREATE INDEX idx_expedientes_deleted_at ON public.expedientes USING btree (deleted_at) WHERE (deleted_at IS NULL);


--
-- Name: idx_pacientes_carnet; Type: INDEX; Schema: public; Owner: pandora
--

CREATE INDEX idx_pacientes_carnet ON public.pacientes USING btree (carnet);


--
-- Name: idx_pacientes_deleted_at; Type: INDEX; Schema: public; Owner: pandora
--

CREATE INDEX idx_pacientes_deleted_at ON public.pacientes USING btree (deleted_at) WHERE (deleted_at IS NULL);


--
-- Name: idx_profesionales_deleted_at; Type: INDEX; Schema: public; Owner: pandora
--

CREATE INDEX idx_profesionales_deleted_at ON public.profesionales USING btree (deleted_at) WHERE (deleted_at IS NULL);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: pandora
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: pandora
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: carreras carreras_facultad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.carreras
    ADD CONSTRAINT carreras_facultad_id_foreign FOREIGN KEY (facultad_id) REFERENCES public.facultades(id) ON DELETE RESTRICT;


--
-- Name: contactos_paciente contactos_paciente_paciente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.contactos_paciente
    ADD CONSTRAINT contactos_paciente_paciente_id_foreign FOREIGN KEY (paciente_id) REFERENCES public.pacientes(codigo) ON DELETE CASCADE;


--
-- Name: expedientes expedientes_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.expedientes
    ADD CONSTRAINT expedientes_area_id_foreign FOREIGN KEY (area_id) REFERENCES public.areas(id) ON DELETE RESTRICT;


--
-- Name: expedientes expedientes_paciente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.expedientes
    ADD CONSTRAINT expedientes_paciente_id_foreign FOREIGN KEY (paciente_id) REFERENCES public.pacientes(codigo) ON DELETE CASCADE;


--
-- Name: pacientes pacientes_carrera_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_carrera_id_foreign FOREIGN KEY (carrera_id) REFERENCES public.carreras(id) ON DELETE RESTRICT;


--
-- Name: pacientes pacientes_creado_por_profesional_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.pacientes
    ADD CONSTRAINT pacientes_creado_por_profesional_id_foreign FOREIGN KEY (creado_por_profesional_id) REFERENCES public.profesionales(id) ON DELETE RESTRICT;


--
-- Name: permission_role permission_role_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.permission_role
    ADD CONSTRAINT permission_role_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permisos(id) ON DELETE CASCADE;


--
-- Name: permission_role permission_role_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.permission_role
    ADD CONSTRAINT permission_role_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: profesionales profesionales_area_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.profesionales
    ADD CONSTRAINT profesionales_area_id_foreign FOREIGN KEY (area_id) REFERENCES public.areas(id) ON DELETE RESTRICT;


--
-- Name: profesionales profesionales_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.profesionales
    ADD CONSTRAINT profesionales_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: role_user role_user_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.role_user
    ADD CONSTRAINT role_user_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: role_user role_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: pandora
--

ALTER TABLE ONLY public.role_user
    ADD CONSTRAINT role_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: pg_database_owner
--

GRANT USAGE ON SCHEMA public TO pandora_app;


--
-- Name: TABLE areas; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.areas TO pandora_app;


--
-- Name: SEQUENCE areas_id_seq; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,USAGE ON SEQUENCE public.areas_id_seq TO pandora_app;


--
-- Name: TABLE cache; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.cache TO pandora_app;


--
-- Name: TABLE cache_locks; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.cache_locks TO pandora_app;


--
-- Name: TABLE carreras; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.carreras TO pandora_app;


--
-- Name: SEQUENCE carreras_id_seq; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,USAGE ON SEQUENCE public.carreras_id_seq TO pandora_app;


--
-- Name: TABLE contactos_paciente; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.contactos_paciente TO pandora_app;


--
-- Name: TABLE expedientes; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.expedientes TO pandora_app;


--
-- Name: TABLE facultades; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.facultades TO pandora_app;


--
-- Name: SEQUENCE facultades_id_seq; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,USAGE ON SEQUENCE public.facultades_id_seq TO pandora_app;


--
-- Name: TABLE migrations; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.migrations TO pandora_app;


--
-- Name: SEQUENCE migrations_id_seq; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,USAGE ON SEQUENCE public.migrations_id_seq TO pandora_app;


--
-- Name: TABLE pacientes; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.pacientes TO pandora_app;


--
-- Name: TABLE permisos; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.permisos TO pandora_app;


--
-- Name: SEQUENCE permisos_id_seq; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,USAGE ON SEQUENCE public.permisos_id_seq TO pandora_app;


--
-- Name: TABLE permission_role; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.permission_role TO pandora_app;


--
-- Name: TABLE profesionales; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.profesionales TO pandora_app;


--
-- Name: TABLE role_user; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.role_user TO pandora_app;


--
-- Name: TABLE roles; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.roles TO pandora_app;


--
-- Name: SEQUENCE roles_id_seq; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,USAGE ON SEQUENCE public.roles_id_seq TO pandora_app;


--
-- Name: TABLE sessions; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.sessions TO pandora_app;


--
-- Name: TABLE users; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE public.users TO pandora_app;


--
-- Name: SEQUENCE users_id_seq; Type: ACL; Schema: public; Owner: pandora
--

GRANT SELECT,USAGE ON SEQUENCE public.users_id_seq TO pandora_app;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: public; Owner: pandora
--

ALTER DEFAULT PRIVILEGES FOR ROLE pandora IN SCHEMA public GRANT SELECT,USAGE ON SEQUENCES TO pandora_app;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: pandora
--

ALTER DEFAULT PRIVILEGES FOR ROLE pandora IN SCHEMA public GRANT SELECT,INSERT,DELETE,UPDATE ON TABLES TO pandora_app;


--
-- PostgreSQL database dump complete
--

\unrestrict 3dRmqWWrblrinc4WqFrjomOEYo2HvKm1LvHO05qo17e9NE5pi2LWdfcr9c9IN1H

