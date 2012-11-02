# Future Stuff
* Provide OAuth 1 three legged proxy again for this thingy?!
* Maybe also implement something else than @me support, preferably not!

Limit the IdPs using SAML scoping
* a certain client can only authenticate at certain IdPs 
* so a list of client_id <--> IdPs needs to be made somewhere
* scope the saml idp stuff in simplesamlphp

# Available Calls
    /groups/@me
    /people/<GROUPID>   ?
    /people/@me         ?

# Implement API to manage group providers
    /api.php/providers/<id>     POST, PUT, DELETE, GET
    /api.php/clients/<id>       POST, PUT, DELETE, GET

the management interface should obtain a list of all OAuth clients registered
so they can be used to register the client/provider combinations

# OAuth Clients
* standard php-oauth registration...

# External Group Providers
    {
        "basic_pass": "bar", 
        "basic_user": "foo", 
        "contact_email": null, 
        "endpoint": "http://localhost/oauth/php-voot-provider/voot.php", 
        "filter": [
            "foo"
        ], 
        "id": "demo", 
        "name": "Demo Group Provider"
    }

# Access Control
Limit access to a group provider per client
   
    {
        "allowGroupList": true, 
        "allowPeople": true, 
        "allowPeopleList": true, 
        "attributeRelease": [
            "id", 
            "displayName",
        ], 
        "id": "client_foo", 
        "allowedProviders": [
            "demo", 
            "bar"
        ]
    }

# UI
* OAuth client registration (already exists)
* EGP configuration (needs to be built)
* Linking clients to EGPs (needs to be built)

Maybe create one HTML5 interface to do everything instead of three separate 
ones if the entitlements are right?

## Group Provider

                          <add>
    +--------------------------+
    | Name     Actions         |
    |                          |
    | Foo      <edit> <delete> |
    | Bar      <edit> <delete> |
    +--------------------------+

## Edit/Add Group Provider

    +----------------------------------+
    | *Foo*                            |
    |                                  |
    | ID            [ foo            ] |
    | Name          [ Foo            ] |
    | Endpoint      [ https://exampl ] |
    | Basic User    [ foo            ] |
    | Basic Pass    [ bar            ] |
    | Contact Email [ f@example.org  ] |
    |                                  |
    | Allow Access To Clients          |
    | [x] FooClient      [ ] Mekker    |
    | [x] BarClient      [ ] Boeh      |
    | [ ] Gaap           [x] Blup      |
    |                                  |
    |                   [Close] [Save] |
    +----------------------------------+

## Client

The client list is retrieved from the OAuth server API.

                             <add>
    +-----------------------------+
    | Name        Actions         |
    |                             |
    | FooClient   <edit> <delete> | 
    | BarClient   <edit> <delete> |
    +-----------------------------+

## Edit Client

This shows the default configuration

    +----------------------------------+
    | *FooClient*                      |
    |                                  |
    | Allow Access To Group Providers  |
    | [ ] Foo           [ ] XYZ        |
    | [ ] Bar           [ ] ABC        |
    | [ ] FooBar        [ ] Blah       |
    |                                  |
    | Allowed API Calls                |
    | [x] Groups                       |    Allow client to retrieve details of the groups the user is a member of
    | [ ] People                       |    Allow client to retrieve member details of groups the user is a member of
    |                                  |
    | Attributes for People Calls      |
    | [x] id                           |    ID is persistent user identifier (hopefully, but not the same as the SP
    | [ ] displayName                  |    retrieves using SAML... which is a problem!
    | [ ] emails                       |
    | [ ] name                         |
    |                                  |
    |                   [Close] [Save] |
    +----------------------------------+


