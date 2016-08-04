# joomla_waauth
WildApricot Authentication Module for Joomla

This was completely based on the code from external auth module for Joomla and highly customised for the Audax Australia cycling club. It uses the WildApricot member ID number as the Joomla ID and we use the plugin users_same_email as WA users may have no e-mail address sp "duplicate" joomla usernames would result. This should allow members to login with e-mail address, or if they don't have an e-mail address then the Joomla ID number.

Also has code to add the user to a group or remove from a group based on membership status.

We also use the WildApricot Groups feature. The example code checks if the member is Groups "Ride Organisers" pr "Homologation Admins" and if so will add/remote the Joomla user into a configurable Joomla group. This needs code modification as the string names are hard coded.

Some of the WildApricot contact veriables are copied and set in the Joomla profile (for example address1, city etc). This will also probably need code customisation.
