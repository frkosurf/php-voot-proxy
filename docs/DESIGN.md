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
