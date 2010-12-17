//
//  SSMAccount.h
//  Sosumi
//
//  Created by Tyler Hall on 12/9/10.
//  Copyright 2010 Click On Tyler, LLC. All rights reserved.
//

#import <Cocoa/Cocoa.h>


@interface SSMAccount : NSObject {
	NSString *username_;
	NSString *password_;
	NSString *partition_;
	NSMutableDictionary *devices_;
	NSDictionary *serverContext_;
	NSTimer *refreshTimer_;
}

@property (nonatomic, retain) NSString *username;
@property (nonatomic, retain) NSString *password;
@property (nonatomic, retain) NSString *partition;
@property (nonatomic, retain) NSMutableDictionary *devices;
@property (nonatomic, retain) NSDictionary *serverContext;
@property (nonatomic, retain) NSTimer *refreshTimer;

@end
