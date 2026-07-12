Feature: Service health
    In order to operate the connector safely
    As an administrator
    I need the service to report whether it is healthy

    Scenario: Check service is healthy
        When I request the health endpoint
        Then the service should report it is healthy
