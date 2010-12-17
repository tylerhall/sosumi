/* Copyright (c) 2010 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

//
//  GTMHTTPFetcherService.m
//

#import "GTMHTTPFetcherService.h"

@implementation GTMHTTPFetcherService

@synthesize runLoopModes = runLoopModes_;
@synthesize credential = credential_;
@synthesize proxyCredential = proxyCredential_;
@synthesize cookieStorageMethod = cookieStorageMethod_;
@synthesize fetchHistory = fetchHistory_;

@dynamic shouldCacheETaggedData;

- (id)init {
  if ((self = [super init]) != nil) {
    fetchHistory_ = [[GTMHTTPFetchHistory alloc] init];
    cookieStorageMethod_ = kGTMHTTPFetcherCookieStorageMethodFetchHistory;
  }
  return self;
}

- (void)dealloc {
  [fetchHistory_ release];
  [runLoopModes_ release];
  [credential_ release];
  [proxyCredential_ release];
  [super dealloc];
}

#pragma mark Generate a new fetcher

- (GTMHTTPFetcher *)fetcherWithRequest:(NSURLRequest *)request {
  GTMHTTPFetcher *fetcher = [GTMHTTPFetcher fetcherWithRequest:request];

  [fetcher setFetchHistory:[self fetchHistory]];
  [fetcher setRunLoopModes:[self runLoopModes]];
  [fetcher setCookieStorageMethod:[self cookieStorageMethod]];
  [fetcher setCredential:[self credential]];
  [fetcher setProxyCredential:[self proxyCredential]];

  return fetcher;
}

#pragma mark Fetch history settings

// Turn on data caching to receive a copy of previously-retrieved objects.
// Otherwise, fetches may return status 304 (No Change) rather than actual data
- (void)setShouldCacheETaggedData:(BOOL)flag {
  BOOL wasCaching = [self shouldCacheETaggedData];

  [[self fetchHistory] setShouldCacheETaggedData:flag];

  if (wasCaching && !flag) {
    // users expect turning off caching to free up the cache memory
    [self clearETaggedDataCache];
  }
}

- (BOOL)shouldCacheETaggedData {
  return [[self fetchHistory] shouldCacheETaggedData];
}

- (void)setETaggedDataCacheCapacity:(NSUInteger)totalBytes {
  [[self fetchHistory] setMemoryCapacity:totalBytes];
}

- (NSUInteger)ETaggedDataCacheCapacity {
  return [[self fetchHistory] memoryCapacity];
}

// reset the ETag cache to avoid getting a Not Modified status
// based on prior queries
- (void)clearETaggedDataCache {
  [[self fetchHistory] clearETaggedDataCache];
}

- (void)clearHistory {
  [self clearETaggedDataCache];
  [[self fetchHistory] removeAllCookies];
}

@end
