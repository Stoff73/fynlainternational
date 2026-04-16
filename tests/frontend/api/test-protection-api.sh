#!/bin/bash

# Protection API Integration Tests
# Run this script to test the Protection API endpoints

API_BASE="http://127.0.0.1:8000/api"
TIMESTAMP=$(date +%s)
TEST_EMAIL="apitest${TIMESTAMP}@test.com"

echo "🧪 Protection API Integration Tests"
echo "===================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

# Helper function to test endpoint
test_endpoint() {
    local test_name="$1"
    local expected_code="$2"
    local response="$3"

    local actual_code=$(echo "$response" | head -n 1)

    if [ "$actual_code" == "$expected_code" ]; then
        echo -e "${GREEN}✓${NC} $test_name"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} $test_name (Expected: $expected_code, Got: $actual_code)"
        ((FAILED++))
        return 1
    fi
}

# Test 1: Register user
echo "Test 1: Register test user..."
REGISTER_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_BASE/auth/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"name\": \"API Test User\",
    \"email\": \"$TEST_EMAIL\",
    \"password\": \"Password123!\",
    \"password_confirmation\": \"Password123!\",
    \"date_of_birth\": \"1990-01-01\",
    \"gender\": \"male\",
    \"marital_status\": \"single\"
  }")

HTTP_CODE=$(echo "$REGISTER_RESPONSE" | tail -n 1)
REGISTER_BODY=$(echo "$REGISTER_RESPONSE" | sed '$d')

if [ "$HTTP_CODE" == "201" ]; then
    echo -e "${GREEN}✓${NC} User registration successful"
    ((PASSED++))
    # Extract token using Python for better JSON parsing
    TOKEN=$(echo "$REGISTER_BODY" | python3 -c "import sys, json; print(json.load(sys.stdin).get('token', ''))" 2>/dev/null)
    if [ -z "$TOKEN" ]; then
        # Fallback: try jq if available
        TOKEN=$(echo "$REGISTER_BODY" | jq -r '.token' 2>/dev/null)
    fi
    if [ -z "$TOKEN" ]; then
        # Last resort: grep
        TOKEN=$(echo "$REGISTER_BODY" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
    fi
    echo "Token: ${TOKEN:0:30}..."
else
    echo -e "${RED}✗${NC} User registration failed (HTTP $HTTP_CODE)"
    echo "$REGISTER_BODY"
    ((FAILED++))
    exit 1
fi

echo ""

# Test 2: Fetch protection data (should be empty initially)
echo "Test 2: Fetch protection data..."
PROTECTION_RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$API_BASE/protection" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json")

test_endpoint "Fetch protection data" "200" "$PROTECTION_RESPONSE"
echo ""

# Test 3: Unauthenticated request (should fail)
echo "Test 3: Reject unauthenticated request..."
UNAUTH_RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$API_BASE/protection" \
  -H "Accept: application/json")

test_endpoint "Reject unauthenticated request" "401" "$UNAUTH_RESPONSE"
echo ""

# Test 4: Create protection profile
echo "Test 4: Create protection profile..."
PROFILE_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_BASE/protection/profile" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "annual_income": 50000,
    "employment_status": "employed",
    "number_of_dependents": 2,
    "dependents_ages": [5, 8],
    "outstanding_mortgage": 200000,
    "other_debts": 10000,
    "existing_savings": 20000
  }')

test_endpoint "Create protection profile" "201" "$PROFILE_RESPONSE"
echo ""

# Test 5: Create life insurance policy
echo "Test 5: Create life insurance policy..."
LIFE_POLICY_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_BASE/protection/policies/life" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "provider": "Test Insurance Co",
    "policy_number": "TEST123456",
    "sum_assured": 500000,
    "premium_amount": 50,
    "premium_frequency": "monthly",
    "start_date": "2020-01-01",
    "end_date": "2040-01-01",
    "smoker_status": "non-smoker",
    "with_critical_illness": false
  }')

test_endpoint "Create life insurance policy" "201" "$LIFE_POLICY_RESPONSE"
echo ""

# Test 6: Create critical illness policy
echo "Test 6: Create critical illness policy..."
CRITICAL_POLICY_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_BASE/protection/policies/critical-illness" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "provider": "Critical Care Insurance",
    "policy_number": "CRT987654",
    "sum_assured": 100000,
    "premium_amount": 30,
    "premium_frequency": "monthly",
    "start_date": "2020-01-01",
    "end_date": "2040-01-01",
    "conditions_covered": ["cancer", "heart attack", "stroke"]
  }')

test_endpoint "Create critical illness policy" "201" "$CRITICAL_POLICY_RESPONSE"
echo ""

# Test 7: Create income protection policy
echo "Test 7: Create income protection policy..."
INCOME_POLICY_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_BASE/protection/policies/income-protection" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "provider": "Income Protect Ltd",
    "policy_number": "IP555444",
    "benefit_amount": 3000,
    "benefit_frequency": "monthly",
    "deferred_period_weeks": 13,
    "benefit_period_months": 24,
    "premium_amount": 25,
    "premium_frequency": "monthly",
    "start_date": "2021-01-01",
    "end_date": "2041-01-01",
    "occupation_class": "1"
  }')

test_endpoint "Create income protection policy" "201" "$INCOME_POLICY_RESPONSE"
echo ""

# Test 8: Analyze protection coverage
echo "Test 8: Analyze protection coverage..."
ANALYSIS_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_BASE/protection/analyze" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{}')

HTTP_CODE=$(echo "$ANALYSIS_RESPONSE" | tail -n 1)
ANALYSIS_BODY=$(echo "$ANALYSIS_RESPONSE" | sed '$d')

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓${NC} Analyze protection coverage"
    ((PASSED++))
    # Extract adequacy score
    ADEQUACY_SCORE=$(echo "$ANALYSIS_BODY" | grep -o '"adequacy_score":[0-9]*' | cut -d':' -f2)
    echo "   Adequacy Score: $ADEQUACY_SCORE%"
else
    echo -e "${RED}✗${NC} Analyze protection coverage (HTTP $HTTP_CODE)"
    ((FAILED++))
fi
echo ""

# Test 9: Fetch recommendations
echo "Test 9: Fetch recommendations..."
RECOMMENDATIONS_RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$API_BASE/protection/recommendations" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json")

test_endpoint "Fetch recommendations" "200" "$RECOMMENDATIONS_RESPONSE"
echo ""

# Test 10: Run what-if scenario
echo "Test 10: Run what-if scenario..."
SCENARIO_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_BASE/protection/scenarios" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "scenario_type": "death",
    "coverage_adjustment": 100000
  }')

test_endpoint "Run what-if scenario" "200" "$SCENARIO_RESPONSE"
echo ""

# Summary
echo "===================================="
echo "Test Summary:"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo "Total: $((PASSED + FAILED))"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed${NC}"
    exit 1
fi
