Feature: Surrogate provisioning from Discourse user events
    In order to let Discourse users post to the mailing list
    As the connector service
    I need to keep surrogate users in step with Discourse accounts

    Scenario: Provision a surrogate when a user registers
        When Discourse sends a signed "user_created" webhook
        And the queued messages are processed
        Then a surrogate for Discourse user "jane_doe" should exist
        And a subscription request should be emailed to the list
        And the surrogate should be in state "subscribe_sent"

    Scenario: A redelivered user_created webhook is idempotent
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When Discourse sends a signed "user_created" webhook
        And the queued messages are processed
        Then the surrogate should be in state "subscribed"
        And no email should be sent to the list

    Scenario: A name change updates the surrogate without touching the list
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When Discourse sends a signed "user_updated" webhook
        And the queued messages are processed
        Then the surrogate should have display name "Jane D."
        And the surrogate should be in state "subscribed"
        And no email should be sent to the list

    Scenario: An email change re-provisions the surrogate
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "old.address@example.org"
        When Discourse sends a signed "user_updated" webhook
        And the queued messages are processed
        Then an unsubscription request should be emailed to the list
        And a subscription request should be emailed to the list
        And the surrogate should have real email "jane.doe@example.com"
        And the surrogate should be in state "subscribe_sent"

    Scenario: Deleting the Discourse account retires the surrogate
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When Discourse sends a signed "user_destroyed" webhook
        And the queued messages are processed
        Then an unsubscription request should be emailed to the list
        And no surrogate for Discourse user "jane_doe" should exist
