Feature: Surrogate subscription lifecycle
    In order to post to the mailing list on behalf of Discourse users
    As the connector service
    I need each surrogate user to follow the subscription state machine

    Scenario: Complete a subscription
        Given a surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When the subscription request is sent
        And the confirmation request is received
        And the subscription is confirmed
        Then the surrogate should be in state "subscribed"

    Scenario: Unsubscribe a subscribed surrogate
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When the unsubscription starts
        And the unsubscription is confirmed
        Then the surrogate should be in state "unsubscribed"

    Scenario: A surrogate cannot skip the confirmation step
        Given a surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When the subscription request is sent
        Then the surrogate should not be allowed to become subscribed

    Scenario: Disable a surrogate at any point
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When the surrogate is disabled
        Then the surrogate should be in state "disabled"
