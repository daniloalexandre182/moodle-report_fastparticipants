@report @report_fastparticipants
Feature: Teacher can list and filter the course participants with the fast participants report
  In order to find course participants without loading the whole list
  As a teacher
  I need to use the fast participants report filters

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 0         |
      | Course 2 | C2        | 0        | 1         |
    And the following "users" exist:
      | username   | firstname | lastname | email                   |
      | teacher1   | Teacher   | One      | teacher1@example.com    |
      | teacher2   | Teacher   | Two      | teacher2@example.com    |
      | mariasouza | Maria     | Souza    | maria.souza@example.com |
      | joaosilva  | Joao      | Silva    | joao.silva@example.com  |
      | anacosta   | Ana       | Costa    | ana.costa@example.com   |
    And the following "course enrolments" exist:
      | user       | course | role           |
      | teacher1   | C1     | editingteacher |
      | mariasouza | C1     | student        |
      | joaosilva  | C1     | student        |
      | anacosta   | C1     | student        |
      | teacher1   | C2     | editingteacher |
      | teacher2   | C2     | teacher        |
      | mariasouza | C2     | student        |
      | joaosilva  | C2     | student        |
      | anacosta   | C2     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | G1       |
      | Group B | C1     | G2       |
      | Group A | C2     | G3       |
      | Group B | C2     | G4       |
    And the following "group members" exist:
      | user       | group |
      | mariasouza | G1    |
      | joaosilva  | G2    |
      | mariasouza | G3    |
      | teacher2   | G3    |
      | joaosilva  | G4    |

  Scenario: Opening the report only shows the filter form until the search is submitted
    Given I am on the "C1" "Course" page logged in as "teacher1"
    When I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    Then "id_searchfield" "field" should exist
    And "id_search" "field" should exist
    And I should see "Use the filters and click Search to list the participants."
    And "report-fastparticipants-participants" "table" should not exist
    And I should not see "Maria Souza"
    And I should not see "Joao Silva"
    And I should not see "Ana Costa"
    When I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    Then "report-fastparticipants-participants" "table" should exist
    And I should see "Maria Souza" in the "report-fastparticipants-participants" "table"
    And I should see "Joao Silva" in the "report-fastparticipants-participants" "table"
    And I should see "Ana Costa" in the "report-fastparticipants-participants" "table"
    And I should see "Teacher One" in the "report-fastparticipants-participants" "table"
    And I should not see "Use the filters and click Search to list the participants."

  Scenario: Searching by partial full name shows only the matching user
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    When I set the field "id_searchfield" to "Full name"
    And I set the field "id_search" to "ria sou"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    Then I should see "Maria Souza" in the "report-fastparticipants-participants" "table"
    And I should not see "Joao Silva" in the "report-fastparticipants-participants" "table"
    And I should not see "Ana Costa" in the "report-fastparticipants-participants" "table"

  Scenario: Filtering by role
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    When I set the field "id_roleid" to "Teacher"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    Then I should see "Teacher One" in the "report-fastparticipants-participants" "table"
    And I should not see "Maria Souza" in the "report-fastparticipants-participants" "table"
    And I should not see "Joao Silva" in the "report-fastparticipants-participants" "table"
    And I should not see "Ana Costa" in the "report-fastparticipants-participants" "table"

  Scenario: Filtering by group
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    When I set the field "id_groupid" to "Group B"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    Then I should see "Joao Silva" in the "report-fastparticipants-participants" "table"
    And I should not see "Maria Souza" in the "report-fastparticipants-participants" "table"
    And I should not see "Ana Costa" in the "report-fastparticipants-participants" "table"
    And I should not see "Teacher One" in the "report-fastparticipants-participants" "table"

  Scenario: In separate groups mode a non-editing teacher only sees the users of their own group
    Given I am on the "C2" "Course" page logged in as "teacher2"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    When I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    Then I should see "Maria Souza" in the "report-fastparticipants-participants" "table"
    And I should not see "Joao Silva" in the "report-fastparticipants-participants" "table"
    And I should not see "Ana Costa" in the "report-fastparticipants-participants" "table"
    And the "id_groupid" select box should contain "Group A"
    And the "id_groupid" select box should not contain "Group B"

  Scenario: The report is listed in the course reports and in the report selector
    Given I am on the "C1" "Course" page logged in as "teacher1"
    When I navigate to "Reports" in current page administration
    Then I should see "Fast participants" in the "region-main" "region"
    And I click on "Fast participants" "link" in the "region-main" "region"
    And "Report" "field" should exist in the "tertiary-navigation" "region"
    And I should see "Fast participants" in the "tertiary-navigation" "region"
