# Fast participants (report_fastparticipants)

[![Moodle Plugin CI](https://github.com/daniloalexandre182/moodle-report_fastparticipants/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/daniloalexandre182/moodle-report_fastparticipants/actions/workflows/ci.yml)

Course report that replaces the slow core participants page (`/user/index.php?id=X`) in courses with
many enrolled users. It opens with only a filter form and lists users after the search is submitted.

*Versão em português abaixo.*

## Why

In big courses `/user/index.php` runs the full participants query as soon as the page opens, counts
every enrolment, loads roles, groups and last access for everyone and re-queries through AJAX on each
interaction. This plugin avoids that:

- No user query at all when the page is opened: only the filter form is shown.
- Lean SQL: role, group, enrolment method and status filters are semijoins (`EXISTS`), so no row
  multiplication and no heavy `GROUP BY`.
- Roles, groups and other extra data are loaded in batch only for the users on the current page.
- Page size is capped by an admin setting.
- No AJAX re-query: filters and paging are plain, bookmarkable GET requests.

## Requirements

- Moodle 4.5 (branch `MOODLE_405_STABLE`), PHP 8.1 – 8.3.

## Branches

| Moodle version | Branch              |
|----------------|---------------------|
| 4.5            | `MOODLE_405_STABLE` |
| Development    | `main`              |

## Installation

Using git, from the Moodle root:

```bash
git clone -b MOODLE_405_STABLE https://github.com/daniloalexandre182/moodle-report_fastparticipants.git report/fastparticipants
```

Or download the ZIP and extract it to `report/fastparticipants`. Then visit
*Site administration → Notifications* to complete the installation.

## Usage

1. Open a course and go to *Reports → Fast participants* (or `/report/fastparticipants/index.php?id=ID`).
2. Fill in the filters and click *Search*. Submitting with empty filters lists every participant.
3. Use the bulk actions, edit roles and groups inline, or download the filtered list.

## Features

- Filters: name or identity field (email, username, ID number, custom profile fields...), role
  (including "no role"), group (including "no group"), enrolment status, enrolment method and last
  course access.
- Bulk actions on selected users, including "select all N matching users".
- Inline role and group editing.
- "Enrolment ends" column: shows the end date of each enrolment and edits it in a modal (same permissions
  as the core "Edit enrolment" action: the enrolment method must allow it and `enrol/<method>:manage`).
- Export of the whole filtered list to CSV or Excel.
- Bookmarkable URLs.

## Settings

*Site administration → Plugins → Reports → Fast participants*:

- **Replace the Participants page** (`replaceparticipants`, default off): the course "Participants"
  navigation item points to this report.
- **Maximum rows per page** (`maxperpage`, default 500).
- **Maximum users for "select all"** (`maxselectall`, default 5000).

## Capabilities

- `report/fastparticipants:view` – view the report (non-editing teacher, teacher and manager by
  default; students are not allowed). Access also requires the standard right to view participants
  in the course.

## Privacy

The plugin does not store any personal data (privacy null provider).

## License

GNU GPL v3 or later. See the `LICENSE` file.

---

## Português

Relatório de curso que substitui a lenta página nativa de participantes (`/user/index.php?id=X`) em
cursos com muitos usuários. A página abre apenas com o formulário de filtros e só lista os usuários
depois que a busca é enviada.

### Por que

Em cursos grandes, `/user/index.php` executa a consulta completa assim que abre, conta todas as
inscrições, carrega papéis, grupos e último acesso de todos e refaz consultas via AJAX a cada
interação. Este plugin evita isso:

- Nenhuma consulta de usuários ao abrir a página: apenas o formulário de filtros.
- SQL enxuto: filtros de papel, grupo, método de inscrição e situação usam semijoins (`EXISTS`).
- Papéis, grupos e demais dados são carregados em lote somente para os usuários da página atual.
- Tamanho da página limitado por configuração do administrador.
- Sem AJAX: filtros e paginação são requisições GET simples e salváveis nos favoritos.

### Requisitos

- Moodle 4.5 (branch `MOODLE_405_STABLE`), PHP 8.1 – 8.3.

### Instalação

A partir da raiz do Moodle:

```bash
git clone -b MOODLE_405_STABLE https://github.com/daniloalexandre182/moodle-report_fastparticipants.git report/fastparticipants
```

Ou extraia o ZIP em `report/fastparticipants` e acesse *Administração do site → Notificações*.

### Uso

1. No curso, acesse *Relatórios → Fast participants* (ou `/report/fastparticipants/index.php?id=ID`).
2. Preencha os filtros e clique em *Buscar*. Sem filtros, lista todos os participantes.
3. Use as ações em lote, edite papéis e grupos na própria lista ou baixe a lista filtrada.

### Recursos

- Filtros por nome ou campo de identificação, papel (inclusive "sem papel"), grupo (inclusive "sem
  grupo"), situação da inscrição, método de inscrição e último acesso ao curso.
- Ações em lote, inclusive "selecionar todos os N usuários encontrados".
- Edição de papéis e grupos na própria lista.
- Coluna "Inscrição termina": mostra a data de término de cada inscrição e permite editá-la em um modal
  (mesmas permissões da ação "Editar inscrição" do core: o método de inscrição precisa permitir e
  `enrol/<método>:manage`).
- Exportação da lista filtrada completa para CSV ou Excel.

### Configurações

*Administração do site → Plugins → Relatórios → Fast participants*:

- **Replace the Participants page** (`replaceparticipants`, padrão desligado): o item "Participantes"
  do curso passa a apontar para este relatório.
- **Maximum rows per page** (`maxperpage`, padrão 500).
- **Maximum users for "select all"** (`maxselectall`, padrão 5000).

### Capacidades

- `report/fastparticipants:view` – ver o relatório (professor, professor editor e gerente por padrão;
  estudantes não têm acesso). Também é exigida a permissão padrão de ver os participantes do curso.

### Privacidade

O plugin não armazena dados pessoais (provedor de privacidade nulo).

### Licença

GNU GPL v3 ou posterior.
