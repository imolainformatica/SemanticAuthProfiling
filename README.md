--------------------------------------------------------------------------
README for the SemanticAuthProfiling extension
Copyright © 2019 Giacomo Lorenzo
Licenses: GNU General Public Licence (GPL)
          GNU Free Documentation License (GFDL)
--------------------------------------------------------------------------
# Installation
```php
wfLoadExtension( 'SemanticAuthProfiling' );
```
# Configuration


## Group Creation
### Create a groups to profile
```php
$wgGroupPermissions['Mygroup1']=$wgGroupPermissions['user'];
$wgGroupPermissions['Mygroup2']= $wgGroupPermissions['user'];
$wgGroupPermissions['Mygroup3'] = $wgGroupPermissions['user'];
$wgGroupPermissions['Mygroup4'] = $wgGroupPermissions['user'];
$wgGroupPermissions['Mygroup5'] = $wgGroupPermissions['user'];
$wgGroupPermissions['Mygroup6'] = $wgGroupPermissions['user'];
$wgGroupPermissions['Mygroup7'] = $wgGroupPermissions['user'];
$wgGroupPermissions['Mygroup8']=$wgGroupPermissions['user'];
```


### Default Time Refresh Page Admin
This timer need if use the page admin profiling functionality
```php
$wgSelectiveActionResetTime = 60;
```



### default permission 
There is a default values if one or more groups are not defined then use the default permission to profiling that groups
```php
$wgSemanticAuthProfilingDefaultPermissionEdit=false;
$wgSemanticAuthProfilingDefaultPermissionView=false;
$wgSemanticAuthProfilingDefaultPermissionMove=false;
$wgSemanticAuthProfilingDefaultPermissionCreate=false;
```

### Profiling
Define profiling for each group need to declare a row for each group need to profiling
```php
$wgSelectiveActionViewCategories ['Gruppo-Wiki']['Category-value']= true;
$wgSelectiveActionEditCategories ['IT_Project_Manager']['Project'] = true;
$wgSelectiveActionMoveCategories['Gruppo-Wiki']['Category-value']= true;
$wgSelectiveActionCreateCategories['Gruppo-Wiki']['Category-value']= true;
$wgSelectiveActionDeleteCategories['Gruppo-Wiki']['Category-value']= true;
```

### Wildcard 
need to manage an action for all categories
```php
$wgSelectiveActionViewCategories ['Gruppo-Wiki']['*']= true;
//edit
$wgSelectiveActionEditCategories ['Gruppo-Wiki']['*'] = false;
//move 
$wgSelectiveActionMoveCategories ['Gruppo-Wiki']['*']  =false;
// create
$wgSelectiveActionCreateCategories ['Gruppo-Wiki']['*'] = false;
```



