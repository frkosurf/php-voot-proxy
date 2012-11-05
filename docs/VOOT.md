# Introduction
VOOT 3.0 is a simple protocol for cross-domain read-only access to information
about persons and their group membership within an organization. It can be seen 
as making LDAP-like information available as a web service.

The API is loosly based on the OpenSocial specification, but this is just 
for historical reasons and not all requirements of OpenSocial are met. Only the 
JSON data format is supported for example.

# Use Cases
All the use cases that are valid for LDAP are also valid for VOOT. For instance,
requesting information about users, their group memberships and the members of
a group. VOOT can however not be used to authenticate users as there is no 
password information available to VOOT.

Web based applications can sometimes interface with LDAP to work with group
memberships to base their authorization on this for instance. It is 
notoriously hard or even impossible to get this working cross-domain in a 
secure fashion. This is where VOOT steps in.

# Authorization
This specification will consider two authorization models:

* Basic Authentication [RFC xxxx] if the VOOT provider trusts the client not to
  abuse full access to the user database;
* OAuth 2.0 [RFC xxxx] if there is minimal trust between the VOOT provider and 
  the client where it is left to the user to authorize the client explicitly
  that wants to access information about just the user that grants the 
  permission.

These modes can for example be combined. A proxy using the VOOT protocol can 
use OAuth 2.0 for authorization towards clients while the proxy requests 
other VOOT endpoints protected by Basic Authentication. This can for example be
helpful in "hub and spoke" identity federations.

< PICTURE OF PROXY >

# API
The API supports three calls.

The API calls make use of `@me` which is a placeholder for the user that 
authorized the client. This of course only works when the application uses OAuth 
2.0 as the access token used by the client is bound to the user that authorized 
it.

For the Basic Authentication case an actual user identifier and group 
identifier need to be specified. It is out of scope how the client obtains 
these identifiers.

## Retrieve Group Membership
This call retrieves a list of all groups the user is a member of.

    /groups/@me

This call MUST be supported. The result can include the following keys with
information about the groups, where only `id` MUST be present:

* `id`
* `title`
* `description`

The `id` field contains a local (to the provider) unique identifier of the 
group. It SHOULD be opague to the client. The `title` field contains the 
short human readable name of the group. The `description` field can contain a 
longer description of the group with possibly its purpose.

## Retrieve Members of a Group
This call retrieves a list of all members of a group the user is a member of.

    /people/@me/{groupId}

Where `{groupId}` is replaced with a group identifier obtained through the
call used to retrieve Group Membership.

This call MAY be supported. The result can include the following keys with
information about the user, where only `id` MUST be present:

* `id`
* `displayName`

The `id` field contains a local (to this provider) unique identifier of the 
user. It SHOULD be opague to the client. The `displayName` field contains the
name by which the user prefers to be addressed and can possibly be set by the
user themselves at the provider. The `displayName` field is OPTIONAL.

## Retrieve User Information
This call retrieves additional information about a user.

    /people/@me

This call MUST be supported. The result can include the following keys with
information about the user, where only `id` MUST be present:

* `id`
* `displayName`
* `commonName`
* `emails`

The `id` field contains a local (to this provider) unique identifier of the 
user. It SHOULD be opague to the client. The `displayName` field contains the
name by which the user prefers to be addressed and can possibly be set by the
user themselves at the provider. The `displayName` field is OPTIONAL. The
`commonName` field contains the official full name of the user. This field 
cannot be modified by the user themselves. The `emails` field contains a list
of email addresses belonging to the user. 

## Request Parameters
The API calls have four OPTIONAL parameters that manipulate the result obtained 
from the provider:

* `sortBy`
* `sortOrder`
* `startIndex`
* `count`

The `sortBy` parameter determines the key in the result that is used for sorting
the groups or group members. The available keys are listed below in the API 
Response section. The `sortOrder` determines the order in which the results are 
sorted. Here, two values are possible: `ascending` and `descending`. These 
parameters are OPTIONAL. It is up to the provider whether or not to sort and 
in what order if these parameters are not present.

The `startIndex` parameter determines the offset at which the start for giving
back results. The `count` parameter indicates the number of results to be
given back. The `startIndex` and `count` parameters can be used to implement
paging by returning only a subset of the results. These parameters are OPTIONAL,
if they are not provided the provider MUST consider `startIndex` equals to `0`
and `count` equal to the total number of items available in the set.

The sorting, if requested, MUST be performed on the provider before considering 
the `startIndex` and `count` parameters.

## Response Parameters
All responses mentioned above have the same format. There are always four keys:

* `startIndex`
* `itemsPerPage`
* `totalResults`
* `entry`

Where `startIndex` contains the offset from which the results are returned, 
this is usually equals to the requested `startIndex` unless this value was not
set or invalid, possibly out of bounds. The `itemsPerPage` contains the actual
number of results in the set, as part of `entry`, returned. The `totalResults`
field contains the full number of elements available, not depending on the
`startIndex` and `count` parameters.

The `entry` key contains a list of items, either groups, people or person 
information. Below are some examples.

## API Examples


    {
        "entry": [
            {
                "description": "Group containing employees.", 
                "id": "urn:groups:demo:employee", 
                "title": "Employees", 
                "voot_membership_role": "admin"
            }, 
            {
                "description": "Group containing everyone at this institute.", 
                "id": "urn:groups:demo:member", 
                "title": "Members", 
                "voot_membership_role": "member"
            }, 
            {
                "description": "Group containing the network administrators.", 
                "id": "urn:groups:demo:networkadmin", 
                "title": "Network Administrators", 
                "voot_membership_role": "admin"
            }
        ], 
        "itemsPerPage": 3, 
        "startIndex": "0", 
        "totalResults": 3
    }


    {
        "entry": [
            {
                "id": "urn:people:demo:jmatson", 
                "voot_membership_role": "member"
            }, 
            {
                "id": "urn:people:demo:mcram", 
                "voot_membership_role": "member"
            }, 
            {
                "id": "urn:people:demo:bmcatee", 
                "voot_membership_role": "member"
            }, 
            {
                "id": "urn:people:demo:mwisdom", 
                "voot_membership_role": "member"
            }, 
            {
                "id": "urn:people:demo:teacher", 
                "voot_membership_role": "member"
            }, 
            {
                "id": "urn:people:demo:jstroud", 
                "voot_membership_role": "member"
            }, 
            {
                "id": "urn:people:demo:admin", 
                "voot_membership_role": "member"
            }
        ], 
        "itemsPerPage": 7, 
        "startIndex": "0", 
        "totalResults": 7
    }


# Privacy
Opague value, @me, restricting people calls etc etc



