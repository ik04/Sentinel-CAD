import joblib
import numpy as np

scaler = joblib.load('models/scaler.pkl')
model = joblib.load("models/logistic_regression_model.pkl")
columns_to_standardize = [2,3,4,7,8]
input = np.array([1.0000e+00, 0.0000e+00, 2.3700e+02, 2.7394e+04, 5.4200e+02,
        0.0000e+00, 0.0000e+00, 8.4280e+00, 1.3660e+03])

def spam_forward(input):
    input = np.array([input])
    data_to_transform = input[:,columns_to_standardize]    
    transformed_data = scaler.transform(data_to_transform)
    standardized_input = input.copy()
    standardized_input[:,columns_to_standardize] = transformed_data
    
    predictions = model.predict_proba(standardized_input)
    spam_pred = predictions[0][1]
    
    return spam_pred
    
print(spam_forward(input))