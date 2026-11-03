@report @report_fastparticipants @javascript
Feature: Teacher can manage participants from the fast participants report
  In order to manage the course participants without the heavy participants page
  As a teacher
  I need to edit roles, enrolments and use the bulk actions from the fast participants report

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username   | firstname | lastname | email                   |
      | teacher1   | Teacher   | One      | teacher1@example.com    |
      | mariasouza | Maria     | Souza    | maria.souza@example.com |
      | joaosilva  | Joao      | Silva    | joao.silva@example.com  |
    And the following "course enrolments" exist:
      | user       | course | role           |
      | teacher1   | C1     | editingteacher |
      | mariasouza | C1     | student        |
      | joaosilva  | C1     | student        |

  Scenario: Edit the roles of a participant inline
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    When I click on "Maria Souza's role assignments" "link"
    And I type "Non-editing teacher"
    And I press the enter key
    And I click on "Save changes" "link"
    Then I should see "Student, Non-editing teacher" in the "Maria Souza" "table_row"
    And I should see "Student" in the "Joao Silva" "table_row"

  Scenario: View the enrolment details of a participant
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    When I click on "Manual enrolments" "icon" in the "Maria Souza" "table_row"
    Then I should see "Enrolment details"
    And I should see "Maria Souza" in the "Full name" "table_row"
    And I should see "Manual enrolments" in the "Enrolment method" "table_row"
    And I click on "Cancel" "button" in the "Enrolment details" "dialogue"

  Scenario: Unenrol a participant from the status column
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    When I click on "Unenrol" "icon" in the "Maria Souza" "table_row"
    And I click on "Unenrol" "button" in the "Unenrol" "dialogue"
    Then I should not see "Maria Souza" in the "report-fastparticipants-participants" "table"
    And I should see "Joao Silva" in the "report-fastparticipants-participants" "table"

  Scenario: Send a message to the selected participants
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    And I set the field "Select Maria Souza" to "1"
    When I set the field "With selected users..." to "Send a message"
    Then "Send message to 1 person" "dialogue" should exist
    And I set the following fields to these values:
      | bulk-message | Hello world |
    And I press "Send message to 1 person"
    And I should see "Message sent to 1 person"

  Scenario: Add a note to the selected participants
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    And I set the field "Select Maria Souza" to "1"
    When I set the field "With selected users..." to "Add a new note"
    Then "Add a new note to 1 person" "dialogue" should exist
    And I set the field "bulk-note" to "Maria needs to pick up her game"
    And I press "Add a new note to 1 person"
    And I should see "Maria Souza" in the "report-fastparticipants-participants" "table"

  Scenario: Bulk edit the manual enrolments of the selected participants
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Fast participants" "link" in the "region-main" "region"
    And I click on "Search" "button" in the "#report-fastparticipants-filter" "css_element"
    And I set the field "Select Maria Souza" to "1"
    When I set the field "With selected users..." to "Edit selected user enrolments"
    And I set the field "Alter status" to "Suspended"
    And I press "Save changes"
    Then "#report-fastparticipants-filter" "css_element" should exist
    And I should see "Suspended" in the "Maria Souza" "table_row"
    And I should not see "Suspended" in the "Joao Silva" "table_row"

  Scenario: The course Participants tab opens the report when replaceparticipants is enabled
    Given the following config values are set as admin:
      | replaceparticipants | 1 | report_fastparticipants |
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I follow "Participants"
    Then "#report-fastparticipants-filter" "css_element" should exist
    And I should see "Use the filters and click Search to list the participants."
    And I should not see "Maria Souza"
