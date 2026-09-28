# Migrate from wpDiscuz or Subscribe to Comments Reloaded

## wpDiscuz

wpDiscuz stores comments as normal WordPress comments, so **every comment appears in Convocept
straight away**: activate Convocept, then deactivate wpDiscuz.

Not carried over yet: wpDiscuz votes and its comment subscriptions. An importer for both is
planned for Convocept 1.1. Until then, keep wpDiscuz installed but inactive so its data stays in
the database for the importer.

Formatting: comments are shown as WordPress stored them. wpDiscuz features that live only in its
own tables or shortcodes (for example inline feedback, ratings or uploaded images) are not shown.

## Subscribe to Comments Reloaded (StCR)

Convocept has its own comment subscriptions: "email me replies", "email me all new comments",
subscribing without commenting, double opt-in and one-click unsubscribe. It replaces StCR.

An importer for StCR's subscribers is planned for Convocept 1.1. Until then, keep StCR installed
but inactive if you want to move its subscribers later: its data stays in the database.

Running both at once sends readers two emails for each new comment, so deactivate StCR when you
turn on Convocept's subscriptions (**Settings → Convocept → Subscriptions**).
