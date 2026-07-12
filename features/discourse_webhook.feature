Feature: Discourse webhook intake
    In order to react to Discourse activity
    As the connector service
    I need to accept signed Discourse webhooks and queue the matching work

    Scenario: Accept a signed user_created event
        When Discourse sends a signed "user_created" webhook
        Then the response should be successful
        And a "ProvisionSurrogate" message should be queued

    Scenario: Accept a signed user_updated event
        When Discourse sends a signed "user_updated" webhook
        Then the response should be successful
        And a "SyncSurrogate" message should be queued

    Scenario: Accept a signed user_destroyed event
        When Discourse sends a signed "user_destroyed" webhook
        Then the response should be successful
        And a "RetireSurrogate" message should be queued

    Scenario: Accept a signed post_created event
        When Discourse sends a signed "post_created" webhook
        Then the response should be successful
        And a "RelayPost" message should be queued

    Scenario: Reject a webhook with an invalid signature
        When Discourse sends a "user_created" webhook with an invalid signature
        Then the response should be unauthorized
        And no message should be queued

    Scenario: Ignore an unknown event
        When Discourse sends a signed "topic_edited" webhook
        Then the response should be successful
        And no message should be queued

    Scenario: Reject a signed webhook with a broken body
        When Discourse sends a signed "user_created" webhook with a broken body
        Then the response should be bad request
        And no message should be queued
