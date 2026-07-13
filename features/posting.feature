Feature: Relaying Discourse posts to the mailing list
    In order to let Discourse users participate in the mailing list
    As the connector service
    I need to send their posts to the list as properly formatted emails

    Scenario: Relay a new topic to the list
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When Discourse sends a signed "post_created" webhook
        And the queued messages are processed
        Then an email should be relayed to the mailing list
        And the relayed email should be from "Jane Doe via Discourse"
        And the relayed email subject should be "Hello list"
        And the relayed email Message-ID should be "topic/3/7@discourse.example.test"
        And the relayed email should not be a reply
        And an outbound message should be recorded for Discourse post 7

    Scenario: Thread a reply against an already-mapped post
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        And Discourse post number 1 in topic 3 resolves to post 7
        And a list message "original@lists.php.net" is mapped to Discourse post 7 in topic 3
        When Discourse sends a signed post_created reply webhook
        And the queued messages are processed
        Then an email should be relayed to the mailing list
        And the relayed email should be in reply to "original@lists.php.net"

    Scenario: Recover the Message-ID of a list-originated parent
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        And Discourse post number 1 in topic 3 resolves to post 5
        And Discourse post 5 was received from the mailing list with Message-ID "listmsg@lists.php.net"
        When Discourse sends a signed post_created reply webhook
        And the queued messages are processed
        Then the relayed email should be in reply to "listmsg@lists.php.net"
        And a list message mapping should be recorded for Discourse post 5

    Scenario: Do not echo a post that itself arrived by email
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When Discourse sends a signed post_created webhook that arrived via email
        And the queued messages are processed
        Then no email should be sent to the list

    Scenario: Ignore posts outside the mirrored category
        Given a subscribed surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When Discourse sends a signed post_created webhook in an unmirrored category
        And the queued messages are processed
        Then no email should be sent to the list

    Scenario: Do not relay a post from an author without a subscribed surrogate
        Given a surrogate user for Discourse user "jane_doe" with email "jane.doe@example.com"
        When Discourse sends a signed "post_created" webhook
        And the queued messages are processed
        Then no email should be sent to the list
