{{ $heading }}

{{ $eventDetails['title'] }}
When: {{ $eventDetails['date'] }}, {{ $eventDetails['time'] }} ({{ $eventDetails['timezone'] }})
Where: {{ $eventDetails['location'] }}

View event: {{ $eventDetails['event_url'] }}
Manage my events: {{ $manageUrl }}
Review or cancel: {{ $cancelUrl }}
