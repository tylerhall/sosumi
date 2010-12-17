//
//  SosumiAppDelegate.m
//  Sosumi
//
//  Created by Tyler Hall on 12/9/10.
//  Copyright 2010 Click On Tyler, LLC. All rights reserved.
//

#import "SosumiAppDelegate.h"
#import "SSMManager.h"

@implementation SosumiAppDelegate

@synthesize window;

- (void)applicationDidFinishLaunching:(NSNotification *)aNotification {
	SSMManager *ssm = [SSMManager sosumiWithDelegate:self];
	[ssm addAccountWithUsername:@"username@me.com" password:@"password"];
}

@end
