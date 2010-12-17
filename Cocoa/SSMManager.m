//
//  SSMManager.m
//  Sosumi
//
//  Created by Tyler Hall on 12/9/10.
//  Copyright 2010 Click On Tyler, LLC. All rights reserved.
//

#import "SSMManager.h"
#import "SSMAccount.h"
#import "GTMHTTPFetcher.h"
#import "NSData+Base64.h"
#import "JSON.h"

@implementation SSMManager

@synthesize delegate=delegate_;
@synthesize accounts=accounts_;

- (id)init {
	self = [super init];
	self.accounts = [[NSMutableArray alloc] init];
	return self;
}

- (void)dealloc {
	[accounts_ release];
	[delegate_ release];
	[super dealloc];
}

+ (SSMManager *)sosumiWithDelegate:(id)delegate {
	SSMManager *ssm = [[[SSMManager alloc] init] autorelease];
	ssm.delegate = delegate;
	return ssm;
}

- (void)addAccountWithUsername:(NSString *)username password:(NSString *)password {
	SSMAccount *acct = [[SSMAccount alloc] init];
	acct.username = username;
	acct.password = password;
	[self.accounts addObject:acct];
	[self getAccountPartition:acct];
	[acct release];
}

- (BOOL)removeAccountWithUsername:(NSString *)username {
	for(int i = 0; i < [self.accounts count]; i++) {
		SSMAccount *acct = [self.accounts objectAtIndex:i];
		if([acct.username isEqualToString:username]) {
			[self.accounts removeObjectAtIndex:i];
			return YES;
		}
	}
	
	return NO;
}

#pragma mark -
#pragma mark FMIP API Methods
#pragma mark -

- (void)getAccountPartition:(SSMAccount *)acct {
	NSString *urlStr = [NSString stringWithFormat:@"https://fmipmobile.me.com/fmipservice/device/%@/initClient", acct.username];
	NSURL *url = [NSURL URLWithString:urlStr];
	NSMutableURLRequest *request = [NSMutableURLRequest requestWithURL:url];
	GTMHTTPFetcher *fetcher = [self getPreparedFMIPRequest:request forAccount:acct];

	NSMutableDictionary *postDict = [[NSMutableDictionary alloc] init];
	NSDictionary *clientContext = [NSDictionary dictionaryWithObjectsAndKeys:@"FindMyiPhone", @"appName",
								   @"1.1", @"appVersion",
								   @"99", @"buildVersion",
								   @"0000000000000000000000000000000000000000", @"deviceUDID",
								   @"109541", @"inactiveTime",
								   @"4.2.1", @"osVersion",
								   [NSNumber numberWithInt:0], @"personID",
								   @"iPhone3,1", @"productType", nil];
	[postDict setValue:clientContext forKey:@"clientContext"];
	NSString *postStr = [postDict JSONRepresentation];
	[fetcher setPostData:[postStr dataUsingEncoding:NSUTF8StringEncoding]];

	[fetcher beginFetchWithCompletionHandler:^(NSData *retrievedData, NSError *error) {
		if([[fetcher responseHeaders] valueForKey:@"X-Apple-Mme-Host"]) {
			acct.partition = [[fetcher responseHeaders] valueForKey:@"X-Apple-Mme-Host"];
			NSLog(@"got partition = %@", acct.partition);
			[self initClient:acct];
		} else {
			NSLog(@"SSMError: getPartition");
			if([self.delegate respondsToSelector:@selector(sosumiDidFail:)]) {
				[self.delegate performSelector:@selector(sosumiDidFail:) withObject:acct];
			}
		}
	}];
}

- (void)initClient:(SSMAccount *)acct {
	NSString *urlStr = [NSString stringWithFormat:@"https://%@/fmipservice/device/%@/initClient", acct.partition, acct.username];
	NSURL *url = [NSURL URLWithString:urlStr];
	NSMutableURLRequest *request = [NSMutableURLRequest requestWithURL:url];
	GTMHTTPFetcher *fetcher = [self getPreparedFMIPRequest:request forAccount:acct];

	NSMutableDictionary *postDict = [[NSMutableDictionary alloc] init];
	NSDictionary *clientContext = [NSDictionary dictionaryWithObjectsAndKeys:@"FindMyiPhone", @"appName",
								   @"1.1", @"appVersion",
								   @"99", @"buildVersion",
								   @"0000000000000000000000000000000000000000", @"deviceUDID",
								   @"109541", @"inactiveTime",
								   @"4.2.1", @"osVersion",
								   [NSNumber numberWithInt:0], @"personID",
								   @"iPhone3,1", @"productType", nil];
	[postDict setValue:clientContext forKey:@"clientContext"];
	NSString *postStr = [postDict JSONRepresentation];
	[fetcher setPostData:[postStr dataUsingEncoding:NSUTF8StringEncoding]];

	[fetcher beginFetchWithCompletionHandler:^(NSData *retrievedData, NSError *error) {
		if(error != nil) {
			NSLog(@"SSMError: initClient");
			if([self.delegate respondsToSelector:@selector(sosumiDidFail:)]) {
				[self.delegate performSelector:@selector(sosumiDidFail:) withObject:acct];
			}			
		} else {
			NSString *response = [[NSString alloc] initWithData:retrievedData encoding:NSUTF8StringEncoding];
			NSDictionary *json = [response JSONValue];
			acct.serverContext = [json valueForKey:@"serverContext"];
			for(NSDictionary *device in [json valueForKey:@"content"]) {
				[acct.devices setValue:[NSDictionary dictionaryWithDictionary:device] forKey:[device valueForKey:@"id"]];
			}
			if([self.delegate respondsToSelector:@selector(sosumiGotDevices:)]) {
				[self.delegate performSelector:@selector(sosumiGotDevices:) withObject:acct];
			}
			acct.refreshTimer = [NSTimer timerWithTimeInterval:10.0 target:self selector:@selector(refreshTimer:) userInfo:acct repeats:NO];
			[[NSRunLoop mainRunLoop] addTimer:acct.refreshTimer forMode:NSDefaultRunLoopMode];
		}
	}];
}

- (void)refreshTimer:(NSTimer*)theTimer {
	[self refreshClient:[theTimer userInfo]];	
}

- (void)refreshClient:(SSMAccount *)acct {
	NSString *urlStr = [NSString stringWithFormat:@"https://%@/fmipservice/device/%@/initClient", acct.partition, acct.username];
	NSURL *url = [NSURL URLWithString:urlStr];
	NSMutableURLRequest *request = [NSMutableURLRequest requestWithURL:url];
	GTMHTTPFetcher *fetcher = [self getPreparedFMIPRequest:request forAccount:acct];
	
	NSMutableDictionary *postDict = [[NSMutableDictionary alloc] init];
	NSDictionary *clientContext = [NSDictionary dictionaryWithObjectsAndKeys:@"FindMyiPhone", @"appName",
								   @"1.1", @"appVersion",
								   @"99", @"buildVersion",
								   @"0000000000000000000000000000000000000000", @"deviceUDID",
								   @"109541", @"inactiveTime",
								   @"4.2.1", @"osVersion",
								   [NSNumber numberWithInt:0], @"personID",
								   @"iPhone3,1", @"productType", nil];
	[postDict setValue:clientContext forKey:@"clientContext"];
	[postDict setValue:acct.serverContext forKey:@"serverContext"];
	NSString *postStr = [postDict JSONRepresentation];
	[fetcher setPostData:[postStr dataUsingEncoding:NSUTF8StringEncoding]];

	[fetcher beginFetchWithCompletionHandler:^(NSData *retrievedData, NSError *error) {
		if(error != nil) {
			NSLog(@"SSMError: refreshClient");
			if([self.delegate respondsToSelector:@selector(sosumiDidFail:)]) {
				[self.delegate performSelector:@selector(sosumiDidFail:) withObject:acct];
			}
		} else {
			NSString *response = [[NSString alloc] initWithData:retrievedData encoding:NSUTF8StringEncoding];
			NSDictionary *json = [response JSONValue];
			acct.serverContext = [json valueForKey:@"serverContext"];

			double refreshInterval = [[acct.serverContext valueForKey:@"callbackIntervalInMS"] doubleValue] / 1000.0;
			acct.refreshTimer = [NSTimer timerWithTimeInterval:refreshInterval target:self selector:@selector(refreshTimer:) userInfo:acct repeats:NO];
			[[NSRunLoop mainRunLoop] addTimer:acct.refreshTimer forMode:NSDefaultRunLoopMode];
			
			NSLog(@"refresh %f", refreshInterval);
			
			for(NSDictionary *device in [json valueForKey:@"content"]) {
				[acct.devices setValue:[NSDictionary dictionaryWithDictionary:device] forKey:[device valueForKey:@"id"]];
			}
			if([self.delegate respondsToSelector:@selector(sosumiUpdatedDevices:)]) {
				[self.delegate performSelector:@selector(sosumiUpdatedDevices:) withObject:acct];
			}
		}
	}];
}

#pragma mark -
#pragma mark Misc Helpers
#pragma mark -

- (GTMHTTPFetcher *)getPreparedFMIPRequest:(NSMutableURLRequest *)request forAccount:(SSMAccount *)acct {
	[request addValue:@"application/json; charset=utf-8" forHTTPHeaderField:@"Content-Type"];
	[request addValue:@"2.0" forHTTPHeaderField:@"X-Apple-Find-Api-Ver"];
	[request addValue:@"UserIdGuest" forHTTPHeaderField:@"X-Apple-Authscheme"];
	[request addValue:@"1.0" forHTTPHeaderField:@"X-Apple-Realm-Support"];
	[request addValue:@"Find iPhone/1.1 MeKit (iPad: iPhone OS/4.2.1)" forHTTPHeaderField:@"User-agent"];
	[request addValue:@"iPad" forHTTPHeaderField:@"X-Client-Name"];
	[request addValue:@"0cf3dc501ff812adb0b202baed4f37274b210853" forHTTPHeaderField:@"X-Client-Uuid"];
	[request addValue:@"en-us" forHTTPHeaderField:@"Accept-Language"];
	
	NSData *credentials64 = [[NSString stringWithFormat:@"%@:%@", acct.username, acct.password] dataUsingEncoding:NSASCIIStringEncoding];
	[request addValue:[NSString stringWithFormat:@"Basic %@", [credentials64 base64EncodedString]] forHTTPHeaderField:@"Authorization"];
	
	return [GTMHTTPFetcher fetcherWithRequest:request];
}

@end
