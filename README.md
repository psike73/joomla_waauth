# joomla_waauth
WildApricot Authentication Module for Joomla

This was completely based on the code from external auth module for Joomla and highly customised for a specific club. It uses the WildApricot member ID number as the Joomla username. WildApricot members should be able to login with the e-mail address they registered with WildApricot and their WildApricot password.

I'm not a Joomla expert or a PHP expert so you should check things for yourself. It's a good start though.

##Features##
- Add/remove Joomla user to a "members" or "nonmembers" group based upon the status of their WildApricot membership.
- Add/remove Joomla user to one or more other Joomla groups based upon the WildApricot groups they are a member of.
- Copy WildApricot field values into the Joomla profile. 
- Set a single Joomla profile variable to different values based upon if the WildApricot status is current or lapsed member.

## Configuration
**Backend Login**
Can WildApricot be used to authenticate a Joomla backend login? YES or NO 

**WA Account ID**
WildApricot account ID which can be obtained from WildApricot Dashboard >> Account  ( Numeric )

**WA Client ID**
WildApricot client ID which can be setup/obtained from WildApricot Settings >> Security >> Authorized Applications
You will need to setup an application with Read Access.

**WA Client Secret**
WildApricot client Secret which can be setup/obtained from WildApricot Settings >> Security >> Authorized Applications
This value is not displayed.

**WA API Key**
WildApricot API Key which can be setup/obtained from WildApricot Settings >> Security >> Authorized Applications
This value is not displayed.

**Certificate Path**
If your Joomla hosting provider doesn't have an up-to-date CA Certificate file and you install your own, this is the fully qualified path to the certificate file. Should only need to add if you get SSL errors displayed. eg. F:/hshome/myhosting/cacert.pem

**Member Group**
The Joomla user will be added to this Joomla group if they are a current member (ie. not lapsed, future renewal due date or a membership level which doesn't expire). Expired/lapsed members will be removed from this group.

**NonMember Group**
The Joomla user will be added to this Joomla group if they are _not_ a current member (ie. they are lapsed, past renewal due date or a contact but not a member). Members who become "current" but were in this group will be removed from this group.

**Groups to synchronise**
This allows the Joomla user to be added to specific Joomla groups based upon their WildApricot Group membership. Multiple entries are allowed, each entry allows selection of the Joomla group and the name of the group in WildApricot (_exact name, case specific_).
_Note:_ If a Joomla user is is no longer a current member in WildApricot (ie. lapsed/expired membership) they will be removed from the Groups in this list until they become current.

**Copy Profile Fields**
Allows certain fields in WildApricot to be copied to the Joomla user's profile. Flag to determine if this is done (YES or NO) 

**Fields to Copy**
These options are only processed if the _Copy Profile Fields_ options is set to _Yes_.
Multiple entries are allowed. Each entry contains two portions, the name of the WildApricot field and the name of the Joomla profile variable to copy the data to. The joomla variables should be entered without the _profile._ prefix. e.g. to copy WildApricot field Suburb into the profile.city variable the fields would be entered "Suburb" and "city". 

**Life Member Levels**
This field contains the exact name(s) of WildApricot Membership Levels which do not expire. Membership is determined to be current based upon Membership Status is not Lapsed and Renewal Date is a future date. However, if you use WildApricot membership levels which do not expire the Renewal Due date is never set and those membership levels needs to be listed. 

**Joomla Profile Status Variable**
A Joomla profile variable can be set to a value if the user is a current member and a different value if the user is not a current member. This is the name Joomla profile variable which be set, excluding the _.profile_ prefix. For example, if the profile variable to be set is _profile.memberstatus_ this field should be set to _.memberstatus_. If left empty then will be ignored.

**Joomla Profile Status Value (Member)**
The value that the profile variable named in _Joomla Profile Status Variable_ will be set to if the Joomla user is a WildApricot current member.  e.g. _Current Member_ 

**Joomla Profile Status Value (NonMember)**
The value that the profile variable named in _Joomla Profile Status Variable_ will be set to if the Joomla user is _not_ a WildApricot current member.  e.g. _Lapsed Membership_ 
