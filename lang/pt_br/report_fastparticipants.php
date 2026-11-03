<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese language strings for report_fastparticipants.
 *
 * @package    report_fastparticipants
 * @category   string
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesssince_never'] = 'Nunca acessou o curso';
$string['anyenrolmethod'] = 'Qualquer método de inscrição';
$string['anylastaccess'] = 'Qualquer momento';
$string['anyrole'] = 'Qualquer papel';
$string['anystatus'] = 'Qualquer situação';
$string['enrolmentstatustext'] = '{$a->instance}: {$a->status}';
$string['eventreportviewed'] = 'Relatório de participantes rápido visualizado';
$string['fastparticipants:view'] = 'Ver relatório de participantes rápido';
$string['maxperpage'] = 'Máximo de linhas por página';
$string['maxperpage_desc'] = 'Limite máximo de participantes exibidos em uma única página do relatório.';
$string['maxselectall'] = 'Máximo de usuários para "selecionar todos"';
$string['maxselectall_desc'] = 'Número máximo de usuários correspondentes que podem ser selecionados de uma só vez com "Selecionar todos os N usuários correspondentes" para ações em lote. Também é limitado pela configuração max_input_vars do PHP. Defina como 0 para desativar o botão.';
$string['notaccessedfor'] = 'Sem acesso há {$a}';
$string['pluginname'] = 'Participantes (rápido)';
$string['privacy:metadata'] = 'O plugin de relatório Participantes (rápido) não armazena nenhum dado pessoal.';
$string['replaceparticipants'] = 'Substituir a página Participantes';
$string['replaceparticipants_desc'] = 'Se ativado, o item "Participantes" da navegação do curso aponta para este relatório em vez da página padrão de participantes.';
$string['searchfield'] = 'Pesquisar em';
$string['searchfield_help'] = 'Escolha o campo em que pesquisar e digite parte do seu valor. A pesquisa não diferencia maiúsculas de minúsculas e encontra qualquer parte do valor. Deixe o texto vazio para listar todos os participantes que correspondem aos demais filtros.';
$string['searchtext'] = 'Texto a pesquisar';
$string['selectallmatching'] = 'Selecionar todos os {$a} usuários correspondentes';
$string['selectalltruncated'] = 'Apenas os primeiros {$a->count} de {$a->total} usuários correspondentes foram selecionados (o limite é {$a->max}).';
$string['selectedallcount'] = 'Todos os {$a} usuários correspondentes selecionados (limpar seleção)';
$string['submittoview'] = 'Use os filtros e clique em Pesquisar para listar os participantes. Deixe o texto vazio para listar todos.';
