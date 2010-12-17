//
//  COTSosumi.h
//  Sosumi
//
//  Created by Tyler Hall on 12/9/10.
//  Copyright 2010 Click On Tyler, LLC. All rights reserved.
//

#import <Cocoa/Cocoa.h>

@class SSMAccount;
@class GTMHTTPFetcher;

@interface SSMManager : NSObject {
	id delegate_;
	NSMutableArray *accounts_;
}

@property (retain, nonatomic) id delegate;
@property (retain, nonatomic) NSMutableArray *accounts;

+ (SSMManager *)sosumiWithDelegate:(id)delegate;
- (void)addAccountWithUsername:(NSString *)username password:(NSString *)password;
- (BOOL)removeAccountWithUsername:(NSString *)username;

- (void)getAccountPartition:(SSMAccount *)acct;
- (void)initClient:(SSMAccount *)acct;
- (void)refreshClient:(SSMAccount *)acct;

- (GTMHTTPFetcher *)getPreparedFMIPRequest:(NSMutableURLRequest *)request forAccount:(SSMAccount *)acct;

@end
