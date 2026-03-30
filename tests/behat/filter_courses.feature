@local @local_mycoursesfilter
Feature: Filter the current user's course cards
  In order to find my own courses quickly
  As an enrolled learner
  I need a filtered view of my course cards

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname     | shortname |
      | Biology 101  | BIO101    |
      | History 101  | HIS101    |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | BIO101 | student |
      | student1 | HIS101 | student |

  Scenario: Filter courses by name query
    When I log in as "student1"
    And I am on the local my courses filter page with query "Biology"
    Then I should see "Biology 101"
    And I should not see "History 101"

  Scenario: Show a helpful message when no course matches the filter
    When I log in as "student1"
    And I am on the local my courses filter page with query "Physics"
    Then I should see "No courses found."
    And I should not see "Biology 101"
    And I should not see "History 101"
