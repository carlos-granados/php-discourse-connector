Feature: Catch-all inbox processing
    In order to complete list subscriptions without human intervention
    As the connector service
    I need to answer ezmlm confirmation emails automatically

    Scenario: Reply to the subscription confirmation request
        Given a surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        And the subscription request is sent
        When the inbox receives an ezmlm subscribe confirmation for the surrogate
        Then a confirmation reply should be emailed to the ezmlm cookie address
        And the surrogate should be in state "confirming"

    Scenario: The welcome message completes the subscription
        Given a surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        And the subscription request is sent
        And the confirmation request is received
        When the inbox receives the ezmlm welcome message for the surrogate
        Then the surrogate should be in state "subscribed"

    Scenario: Reply to an unsubscribe confirmation even without a surrogate record
        When the inbox receives an ezmlm unsubscribe confirmation for an unknown address
        Then a confirmation reply should be emailed to the ezmlm cookie address

    Scenario: Unrelated email is stored for audit and gets no reply
        Given a surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When the inbox receives an unrelated email for the surrogate
        Then the inbound email should be recorded as "other"
        And no email should be sent to the list
